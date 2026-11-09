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

    <div class="table-flex-wrapper">

        <div class="table-container">
            <h1 class="page">ຈຳນວນອໍເດີທີ່ຂາຍທັງໝົດ: <?php echo htmlspecialchars($total_orders); ?> ລາຍການ</h1>
            <table id="orders-table" class="table table-striped">
                <thead>
                    <tr>
                        <th>ວັນທີ</th>
                        <th>ຈຳນວນລູກຄ້າ</th>
                        <th>ຍອດລວມ</th>
                        <th>ຈັດການ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sale_by_days)): ?>
                    <tr>
                        <td colspan="3">ບໍ່ມີຂໍ້ມູນຍອດຂາຍ</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($sale_by_days as $row): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($row['sale_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['order_count']); ?> ຄົນ</td>
                        <td><?php echo number_format($row['total_per_day']); ?> ₭</td>
                        <td>
                            <button class="modalBtn btn btn-primary"
                                data-modal-id="editModal<?php echo $row['sale_date']; ?>">ເບິ່ງສິນຄ້າ</button>
                            <button class="btn btn-print modalBtn"
                                data-modal-id="receiptContent<?php echo $order['order_id']; ?>" title="ພິມໃບບິນ">
                                <img src="../assets/icon/printer.png" alt="Print">
                            </button>
                        </td>

                    </tr>

                    <div class="modal" id="editModal<?php echo $row['sale_date']; ?>">
                        <div class="modal-content">
                            <span class="close">&times;</span>
                            <h5 class="modal-title">ອໍເດີປະຈຳວັນ <?= date('d/m/Y', strtotime($row['sale_date'])) ?></h5>
                            <hr>
                            <?php
                            $orders_in_day_stmt = $conn->prepare("
                                SELECT *
                                FROM orders
                                WHERE order_status = 'shipped'
                                  AND DATE(created_at) = :sale_date
                                ORDER BY created_at DESC
                            ");
                            $orders_in_day_stmt->bindValue(':sale_date', $row['sale_date'], PDO::PARAM_STR);
                            $orders_in_day_stmt->execute();
                            $orders_in_day = $orders_in_day_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($orders_in_day as $order): ?>
                            <div class="order-block" style="margin-bottom:18px;">
                                <b>Order ID:</b> <?= htmlspecialchars($order['order_id']) ?> <br>
                                <b>ວັນທີ:</b> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> <br>
                                <b>ຍອດລວມ:</b> <?= number_format($order['order_total']) ?> ₭
                                <div class="form-group product-list-horizontal"
                                    style="margin-top:8px; margin-bottom:8px;">
                                    <?php
                                    // ดึงสินค้าใน order นี้ (ถ้าอยากแยกไว้ด้านบนอีกก็ทำ map เหมือนกัน)
                                    $item_stmt = $conn->prepare("SELECT p.product_name, p.product_image, oi.quantity FROM order_items oi JOIN product p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
                                    $item_stmt->execute([$order['order_id']]);
                                    $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($items as $item):
                                    ?>
                                    <div class="item-block">
                                        <img src="<?= htmlspecialchars($item['product_image']) ?>" alt="">
                                        <div class="item-info">
                                            <h3><?= htmlspecialchars($item['product_name']) ?></h3>
                                            <div class="quantity-control">
                                                <p>ຈຳນວນ : <?= $item['quantity'] ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <h2 class="page" style="color:rgb(36, 177, 31); font-weight: bold;">ລາຍຮັບລວມ:
                <?php echo number_format($total_amount); ?> ₭</h2>
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