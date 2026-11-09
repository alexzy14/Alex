<?php
include_once '../server/config/check_user.php';
include_once '../server/config/request_url.php';

// รับค่าค้นหา
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_query = '%' . $search_query . '%';

// นับจำนวน supplier
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count_suppliers 
    FROM supplier
    WHERE supplier_name LIKE :search_query
");
// เพื่อดูว่ามีผู้จำหน่ายกี่รายที่ตรงกับคำค้นหา
$count_stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
$count_stmt->execute();
$total_suppliers = $count_stmt->fetchColumn();
// กำหนดให้แสดง 100 รายการต่อหน้า
$per_page = 100;
$total_pages = ceil($total_suppliers / $per_page);
$current_page = isset($_GET['page']) ? max(intval($_GET['page']), 1) : 1;
$current_page = min($current_page, $total_pages);
// ดึงข้อมูลผู้จำหน่ายตามคำค้นหาและหน้าปัจจุบัน
$suppliers = [];
if ($total_suppliers > 0) {
    $start = ($current_page - 1) * $per_page;
    $stmt = $conn->prepare("
        SELECT * FROM supplier
        WHERE supplier_name LIKE :search_query
        ORDER BY supplier_id DESC
        LIMIT :start, :per_page
    ");
    $stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
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
            <input type="text" id="searchInput" name="search" placeholder="ຄົ້ນຫາຜູ້ສະໜອງ" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button class="btn-primary" type="submit">ຄົ້ນຫາ</button>
        </form>
    </div>

    <h1 class="page">ຈຳນວນຜູ້ສະໜອງທັງໝົດ: <?php echo htmlspecialchars($total_suppliers); ?> ລາຍການ</h1>
    <div class="table-container">
        <table id="supplier-table" class="table table-striped">
            <thead>
                <tr>
                    <th>ລຳດັບ</th>
                    <th>ຊື່ຜູ້ສະໜອງ</th>
                    <th>ເບີໂທ</th>
                    <th>ອີເມວ</th>
                    <th>ທີ່ຢູ່</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="6">ບໍ່ມີຂໍ້ມູນຜູ້ສະໜອງ</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $index => $supplier): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($index + 1 + ($current_page - 1) * $per_page); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_phone']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_email']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_address']); ?></td>
                
                        </tr>
                    
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Modal สำหรับเพิ่มผู้จำหน่ายใหม่ -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h5 class="modal-title">ເພີ່ມຜູ້ສະໜອງ</h5>
            <hr>
            <form id="addSupplierForm">
                <div class="form-group">
                    <label for="addSupplierName">ຊື່ຜູ້ສະໜອງ:</label>
                    <input type="text" class="form-control" id="addSupplierName" name="supplier_name" required>
                </div>
                <div class="form-group">
                    <label for="addSupplierPhone">ເບີໂທ:</label>
                    <input type="text" class="form-control" id="addSupplierPhone" name="supplier_phone">
                </div>
                <div class="form-group">
                    <label for="addSupplierEmail">ອີເມວ:</label>
                    <input type="text" class="form-control" id="addSupplierEmail" name="supplier_email">
                </div>
                <div class="form-group">
                    <label for="addSupplierAddress">ທີ່ຢູ່:</label>
                    <input type="text" class="form-control" id="addSupplierAddress" name="supplier_address">
                </div>
                <button type="button" class="btn btn-primary" onclick="addSupplier()">ເພີ່ມຜູ້ສະໜອງ</button>
            </form>
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
<script src="../assets/js/add_supplier.js"></script>
<script src="../assets/js/update_supplier.js"></script>
<script src="../assets/js/delete_supplier.js"></script>
</html>