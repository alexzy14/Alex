<?php
include_once '../server/config/check_user.php';
include_once '../server/config/request_url.php';

// รับค่าค้นหา
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_query = '%' . $search_query . '%';
// ดึงข้อมูลสินค้านำเข้าทั้งหมด
$import_stmt = $conn->prepare("
    SELECT ip.*, c.category_name, u.unit_name, s.supplier_name
    FROM import_product_purchase ip
    LEFT JOIN category c ON ip.import_product_report_category = c.category_id
    LEFT JOIN unit u ON ip.import_product_report_unit = u.unit_id
    LEFT JOIN supplier s ON ip.import_product_report_supplier = s.supplier_id
    ORDER BY ip.import_product_report_id DESC
    LIMIT 100
");
//ดึงข้อมูลสินค้านำเข้าทั้งหมด
$import_stmt->execute();
$imports = $import_stmt->fetchAll(PDO::FETCH_ASSOC);
$import_stmt = $conn->prepare("
    SELECT ip.*, c.category_name, u.unit_name, s.supplier_name
    FROM import_product_purchase ip
    LEFT JOIN category c ON ip.import_product_report_category = c.category_id
    LEFT JOIN unit u ON ip.import_product_report_unit = u.unit_id
    LEFT JOIN supplier s ON ip.import_product_report_supplier = s.supplier_id
    WHERE 
        ip.import_product_report_name LIKE :search_query
        OR c.category_name LIKE :search_query
        OR u.unit_name LIKE :search_query
        OR s.supplier_name LIKE :search_query
    ORDER BY ip.import_product_report_id DESC
    LIMIT 100
");
$import_stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
$import_stmt->execute();
$imports = $import_stmt->fetchAll(PDO::FETCH_ASSOC);
// สรุปยอดรวม order_total ทั้งหมด
$total_amount_stmt = $conn->prepare("
    SELECT SUM(order_total) as total_amount
    FROM orders
    WHERE order_status = 'paid'
      AND (
        express LIKE :search_query
        OR branch_address LIKE :search_query
        OR phone LIKE :search_query
      )
");
// นับจำนวนสินค้านำเข้าทั้งหมด
$count_import_stmt = $conn->prepare("
    SELECT COUNT(*) FROM import_product_purchase
");
$count_import_stmt->execute();
$total_imports = $count_import_stmt->fetchColumn();
$total_amount_stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
$total_amount_stmt->execute();
$total_amount = $total_amount_stmt->fetchColumn();
if (!$total_amount) $total_amount = 0;
// นับจำนวนออเดอร์ที่จ่ายเงินแล้ว
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count_orders
    FROM orders
    WHERE order_status = 'paid'
      AND (
        express LIKE :search_query
        OR branch_address LIKE :search_query
        OR phone LIKE :search_query
      )
");
// เพื่อดูว่ามีออเดอร์ที่จ่ายเงินแล้วกี่รายการที่ตรงกับคำค้นหา
$count_stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
$count_stmt->execute();
$total_orders = $count_stmt->fetchColumn();
// กำหนดให้แสดง 100 รายการต่อหน้า
$per_page = 100;
$total_pages = ceil($total_orders / $per_page);
$current_page = isset($_GET['page']) ? max(intval($_GET['page']), 1) : 1;
$current_page = min($current_page, $total_pages);
// ดึงข้อมูลออเดอร์ที่จ่ายเงินแล้วตามคำค้นหาและหน้าปัจจุบัน
$orders = [];
if ($total_orders > 0) {
    $start = ($current_page - 1) * $per_page;
    $stmt = $conn->prepare("
        SELECT *
        FROM orders
        WHERE order_status = 'paid'
          AND (
            express LIKE :search_query
            OR branch_address LIKE :search_query
            OR phone LIKE :search_query
          )
        ORDER BY order_id DESC
        LIMIT :start, :per_page
    ");
    $stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// แปลงสถานะ
$sale_by_day_stmt = $conn->prepare("
    SELECT DATE(created_at) as sale_date, SUM(order_total) as total_per_day, COUNT(*) as order_count
    FROM orders
    WHERE order_status = 'shipped'
      AND (
        express LIKE :search_query
        OR branch_address LIKE :search_query
        OR phone LIKE :search_query
      )
    GROUP BY DATE(created_at)
    ORDER BY sale_date DESC
    LIMIT 100
");
// เพื่อดูว่ามีออเดอร์ที่จ่ายเงินแล้วกี่รายการที่ตรงกับคำค้นหา
$sale_by_day_stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
$sale_by_day_stmt->execute();
$sale_by_days = $sale_by_day_stmt->fetchAll(PDO::FETCH_ASSOC);


                
?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <?php
    $title = json_decode(file_get_contents('../json/title.json'), true)['title'];
    ?>
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include_once "../includes/icon.php"; ?>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/summary.css">
    <link rel="stylesheet" href="../assets/css/search_admin.css">
    <link rel="stylesheet" href="../assets/css/product_admin.css">
    <link rel="stylesheet" href="../assets/css/edit_product.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.css">
</head>

<body>
    <?php include_once "../includes/header_admin.php"; ?>
    <div class="search-container">
        <form method="GET" action="">
            <input type="text" id="searchInput" name="search" placeholder="ຄົ້ນຫາ"
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button class="btn-primary" type="submit">ຄົ້ນຫາ</button>
        </form>
    </div>
    <div class="calendar-container">
        <label for="calendarInput" class="calendar-label">ເລືອກວັນທີ:</label>
        <input type="date" id="calendarInput" name="calendar_date" class="calendar-input">
    </div>
    <div class="container">
        <div class="table-container1">
            <h1 class="page">ຈຳນວນສິນຄ້າທີ່ໄດ້ຊື້ທັງໝົດ: <?php echo htmlspecialchars($total_imports); ?> ລາຍການ</h1>
            <table id="import-table" class="table table-striped">
                <thead>
                    <tr>
                        <th>ລຳດັບ</th>
                        <th>ຮູບ</th>
                        <th>ສິນຄ້າ</th>
                        <th>ລາຄາ</th>
                        <th>ຈຳນວນ</th>
                        <th>ວັນທີ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($imports)): ?>
                    <tr>
                        <td colspan="9">ບໍ່ມີຂໍ້ມູນສິນຄ້ານຳເຂົ້າ</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($imports as $index => $import): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($index + 1); ?></td>
                        <td>
                            <img data-src="<?php echo htmlspecialchars($import['import_product_report_image']); ?>"
                                src="../assets/image/placeholder.png" alt="Product" loading="lazy" class="lazy">
                        </td>
                        <td><?php echo htmlspecialchars($import['import_product_report_name']); ?></td>
                        <td><?php echo number_format($import['import_product_report_price']); ?> ₭</td>
                        <td><?php echo htmlspecialchars($import['import_product_report_quantity']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($import['import_product_update_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
            // คำนวณราคารวมของสินค้านำเข้า
            $total_import_price = 0;
            foreach ($imports as $import) {
                $total_import_price += $import['import_product_report_price'] * $import['import_product_report_quantity'];
            }
            ?>
            <h2 class="page" style="color:rgb(36, 177, 31); font-weight: bold;">
                ລາຍຈ່າຍລວມ <?php echo number_format($total_import_price); ?> ₭
            </h2>
        </div>

    </div>

</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="../assets/js/lazy_loading.js"></script>
<script src="../assets/js/togglemenu.js"></script>
<script src="../assets/js/dropdown.js"></script>
<script src="../assets/js/profile.js"></script>
<script src="../assets/js/modal_admin.js"></script>
<script src="../assets/js/calendar.js"></script>

</html>