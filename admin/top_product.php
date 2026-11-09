<?php
include_once '../server/config/check_user.php';
include_once '../server/config/request_url.php';

// รับค่าค้นหา
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_query = '%' . $search_query . '%';
$calendar_date = isset($_GET['calendar_date']) ? $_GET['calendar_date'] : '';

// นับจำนวนสินค้า
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count_products 
    FROM product p
    LEFT JOIN category c ON p.category_id = c.category_id
    WHERE
      (p.product_name LIKE :search_query 
      OR p.product_description LIKE :search_query
      OR c.category_name LIKE :search_query)
");
// เพื่อดูว่ามีสินค้ากี่รายการที่ตรงกับคำค้นหา
$count_stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
$count_stmt->execute();
$total_products = $count_stmt->fetchColumn();
// กำหนดให้แสดง 100 รายการต่อหน้า
$per_page = 100;
$total_pages = ceil($total_products / $per_page);
$current_page = isset($_GET['page']) ? max(intval($_GET['page']), 1) : 1;
$current_page = min($current_page, $total_pages);
// ดึงข้อมูลสินค้าตามคำค้นหาและหน้าปัจจุบัน
$products = [];
if ($total_products > 0) {
    $start = ($current_page - 1) * $per_page;
    $stmt = $conn->prepare("
        SELECT p.*, c.category_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        WHERE (p.product_name LIKE :search_query 
          OR p.product_description LIKE :search_query
          OR c.category_name LIKE :search_query)
        ORDER BY p.product_id DESC
        LIMIT :start, :per_page
    ");
    $stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$whereDate = '';
if ($calendar_date) {
    $whereDate = "AND DATE(oi.created_at) = :calendar_date";
}
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        c.category_name,
        SUM(oi.quantity) AS total_sold
    FROM product p
    LEFT JOIN category c ON p.category_id = c.category_id
    LEFT JOIN order_items oi ON p.product_id = oi.product_id
    WHERE 1=1
    $whereDate
    GROUP BY p.product_id
    HAVING total_sold > 0
    ORDER BY total_sold DESC
    LIMIT :start, :per_page
");
if ($calendar_date) {
    $stmt->bindValue(':calendar_date', $calendar_date, PDO::PARAM_STR);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงหมวดหมู่ทั้งหมด
$category_stmt = $conn->prepare("SELECT * FROM category");
$category_stmt->execute();
$categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <h1 class="page">ສິນຄ້າທີ່ຂາຍດີທີ່ສຸດ: <?php echo htmlspecialchars(count($products)); ?> ລາຍການ</h1>
            <table id="orders-table" class="table table-striped">
                <thead>
                    <tr>
                        <th>ລຳດັບ</th>
                        <th>ຮູບ</th>
                        <th>ສິນຄ້າ</th>
                        <th>ລາຄາ</th>
                        <th>ສ່ວນຫຼຸດ (%)</th>
                        <th>ຂາຍໄປແລ້ວ</th>
                        <th>ຫມວດສິນຄ້າ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9">ບໍ່ມີຂໍ້ມູນສິນຄ້າ</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $index => $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($index + 1 + ($current_page - 1) * $per_page); ?></td>
                                <td>
                                    <img data-src="<?php echo htmlspecialchars($product['product_image']); ?>"
                                        src="../assets/image/placeholder.png" alt="Product" loading="lazy" class="lazy">
                                </td>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($product['product_price'])); ?> ກີບ</td>
                                <td><?php echo htmlspecialchars($product['product_discount']); ?></td>
                                <td><?php echo htmlspecialchars($product['total_sold'] ?? 0); ?> ລາຍການ</td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal สำหรับเพิ่มสินค้าใหม่ -->

</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="../assets/js/lazy_loading.js"></script>
<script src="../assets/js/togglemenu.js"></script>
<script src="../assets/js/dropdown.js"></script>
<script src="../assets/js/profile.js"></script>
<script src="../assets/js/modal_admin.js"></script>
<script src="../assets/js/search_admin.js"></script>
<script src="../assets/js/calendar.js"></script>

</html>