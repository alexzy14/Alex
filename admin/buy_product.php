<?php
include_once '../server/config/check_user.php';
include_once '../server/config/request_url.php';

// รับค่าค้นหา
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_query_sql = '%' . $search_query . '%';

// นับจำนวนสินค้านำเข้า
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count_imports 
    FROM import_product_purchase ip
    LEFT JOIN category c ON ip.import_product_report_category = c.category_id
    LEFT JOIN unit u ON ip.import_product_report_unit = u.unit_id
    LEFT JOIN supplier s ON ip.import_product_report_supplier = s.supplier_id
    WHERE 
      ip.import_product_report_name LIKE :search_query 
      OR c.category_name LIKE :search_query   
      OR u.unit_name LIKE :search_query
      OR s.supplier_name LIKE :search_query
");
//เพื่อดูว่ามีสินค้านำเข้ากี่รายการที่ตรงกับคำค้นหา
$count_stmt->bindValue(':search_query', $search_query_sql, PDO::PARAM_STR);
$count_stmt->execute();
$total_imports = $count_stmt->fetchColumn();
//กำหนดให้แสดง 100 รายการต่อหน้า
$per_page = 100;
//คำนวณจำนวนหน้าทั้งหมด
$total_pages = max(ceil($total_imports / $per_page), 1);
$current_page = isset($_GET['page']) ? max(intval($_GET['page']), 1) : 1;
$current_page = min($current_page, $total_pages);
// ดึงข้อมูลสินค้านำเข้าตามคำค้นหาและหน้าปัจจุบัน
$imports = [];
if ($total_imports > 0) {
    $start = ($current_page - 1) * $per_page;
    $stmt = $conn->prepare("
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
        LIMIT :start, :per_page
    ");
    $stmt->bindValue(':search_query', $search_query_sql, PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $imports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ดึงหมวดหมู่ทั้งหมด
$category_stmt = $conn->prepare("SELECT * FROM category");
$category_stmt->execute();
$categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงหน่วยนับทั้งหมด
$unit_stmt = $conn->prepare("SELECT * FROM unit");
$unit_stmt->execute();
$units = $unit_stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงผู้จำหน่ายทั้งหมด
$supplier_stmt = $conn->prepare("SELECT * FROM supplier");
$supplier_stmt->execute();
$suppliers = $supplier_stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="../assets/css/historypay.css">
    <link rel="stylesheet" href="../assets/css/modal_admin.css">
    <link rel="stylesheet" href="../assets/css/search_admin.css">
    <link rel="stylesheet" href="../assets/css/product_admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.css">
</head>

<body>
    <?php include_once "../includes/header_admin.php"; ?>
    <div class="search-container">
        <form method="GET" action="">
            <input type="text" id="searchInput" name="search" placeholder="ຄົ້ນຫາສິນຄ້າທີ່ສັ່ງຊື້"
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button class="btn-primary" type="submit">ຄົ້ນຫາ</button>
        </form>
    </div>

    <h1 class="page">ຈຳນວນສິນຄ້າທີ່ໄດ້ຊື້ທັງໝົດ: <?php echo htmlspecialchars($total_imports); ?> ລາຍການ</h1>

    <div class="table-container">
        <table id="import-table" class="table table-striped">
            <thead>
                <tr>
                    <th>ລຳດັບ</th>
                    <th>ຮູບ</th>
                    <th>ສິນຄ້າ</th>
                    <th>ລາຄານຳເຂົ້າ</th>
                    <th>ຈຳນວນ</th>
                    <th>ຫມວດ</th>
                    <th>ໜ່ວຍ</th>
                    <th>ຜູ້ສະໜອງ</th>
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
                    <td><?php echo htmlspecialchars($index + 1 + ($current_page - 1) * $per_page); ?></td>
                    <td>
                        <img data-src="<?php echo htmlspecialchars($import['import_product_report_image']); ?>"
                            src="../assets/image/placeholder.png" alt="Product" loading="lazy" class="lazy">
                    </td>
                    <td><?php echo htmlspecialchars($import['import_product_report_name']); ?></td>
                    <td><?php echo number_format($import['import_product_report_price']); ?> ₭</td>
                    <td><?php echo htmlspecialchars($import['import_product_report_quantity']); ?></td>
                    <td><?php echo htmlspecialchars($import['category_name']); ?></td>
                    <td><?php echo htmlspecialchars($import['unit_name']); ?></td>
                    <td><?php echo htmlspecialchars($import['supplier_name']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

        </table>
    </div>

</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="../assets/js/lazy_loading.js"></script>
<script src="../assets/js/togglemenu.js"></script>
<script src="../assets/js/dropdown.js"></script>
<script src="../assets/js/profile.js"></script>
<script src="../assets/js/modal_admin.js"></script>

</html>