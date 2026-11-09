<?php
include_once '../server/config/check_user.php';
include_once '../server/config/request_url.php';

// รับค่าค้นหา
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_query = '%' . $search_query . '%';

// นับจำนวน user ที่เป็น user
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count_users 
    FROM users
    WHERE (fullname LIKE :search_query OR username LIKE :search_query OR email LIKE :search_query OR phone LIKE :search_query)
      AND urole = 'user'
");
// เพื่อดูว่ามีผู้ใช้กี่รายที่ตรงกับคำค้นหา
$count_stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
$count_stmt->execute();
$total_users = $count_stmt->fetchColumn();
// กำหนดให้แสดง 100 รายการต่อหน้า
$per_page = 100;
$total_pages = ceil($total_users / $per_page);
$current_page = isset($_GET['page']) ? max(intval($_GET['page']), 1) : 1;
$current_page = min($current_page, $total_pages);
// ดึงข้อมูลผู้ใช้ตามคำค้นหาและหน้าปัจจุบัน
$users = [];
if ($total_users > 0) {
    $start = ($current_page - 1) * $per_page;
    $stmt = $conn->prepare("
        SELECT * FROM users
        WHERE (fullname LIKE :search_query OR username LIKE :search_query OR email LIKE :search_query OR phone LIKE :search_query)
          AND urole = 'user'
        ORDER BY user_id DESC
        LIMIT :start, :per_page
    ");
    $stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <input type="text" id="searchInput" name="search" placeholder="ຄົ້ນຫາຜູ້ໃຊ້" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button class="btn-primary" type="submit">ຄົ້ນຫາ</button>
        </form>
    </div>

    <h1 class="page">ຈຳນວນຜູ້ໃຊ້ (user) ທັງໝົດ: <?php echo htmlspecialchars($total_users); ?> ລາຍການ</h1>
    <div class="table-container">
        <table id="user-table" class="table table-striped">
            <thead>
                <tr>
                    <th>ລຳດັບ</th>
                    <th>ຊື່ຜູ້ໃຊ້</th>
                    <th>ຊື່ຜູ້ໃຊ້ລະບົບ</th>
                    <th>ເບີໂທ</th>
                    <th>ອີເມວ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6">ບໍ່ມີຂໍ້ມູນຜູ້ໃຊ້</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $index => $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($index + 1 + ($current_page - 1) * $per_page); ?></td>
                            <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                    
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