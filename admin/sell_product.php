<?php
include_once '../server/config/check_user.php';
include_once '../server/config/request_url.php';

// รับค่าค้นหา
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_query = '%' . $search_query . '%';

// นับจำนวนออเดอร์ที่จ่ายเงินแล้ว
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count_orders
    FROM orders
    WHERE order_status = 'shipped'
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
        WHERE order_status = 'shipped'
          AND (
            express LIKE :search_query
            OR branch_address LIKE :search_query
            OR phone LIKE :search_query
          )
        ORDER BY order_id DESC
        LIMIT :start, :per_page
    ");
    // เพื่อดูว่ามีออเดอร์ที่จ่ายเงินแล้วกี่รายการที่ตรงกับคำค้นหา
    $stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// ดึงรายการสินค้าที่สั่งซื้อสำหรับแต่ละออเดอร์
$order_items_map = [];
foreach ($orders as $order) {
    $order_id = $order['order_id'];
    $item_stmt = $conn->prepare("SELECT p.product_name, p.product_image, oi.quantity, oi.price 
        FROM order_items oi 
        JOIN product p ON oi.product_id = p.product_id 
        WHERE oi.order_id = ?");
    $item_stmt->execute([$order_id]);
    $order_items_map[$order_id] = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// แปลงสถานะ
$status_map = [
    'pending' => 'ກຳລັງດຳເນິນການ',
    'paid' => 'ຈ່າຍແລ້ວ',
    'shipped' => 'ຈັດສົ່ງແລ້ວ'
];
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
    <link rel="stylesheet" href="../assets/css/view.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Serif+Lao:wght@100;200;300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.css">
</head>

<body>
    <?php include_once "../includes/header_admin.php"; ?>
    <div class="search-container">
        <form method="GET" action="">
            <input type="text" id="searchInput" name="search" placeholder="ຄົ້ນຫາເບີໂທ, ທີ່ຢູ່ສາຂາ, ຫຼື ປະເພດຂົນສົ່ງ"
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button class="btn-primary" type="submit">ຄົ້ນຫາ</button>
        </form>
    </div>
    <h1 class="page">ຈຳນວນອໍເດີທີ່ຂາຍທັງໝົດ: <?php echo htmlspecialchars($total_orders); ?> ລາຍການ</h1>
    <div class="table-container">
        <table id="orders-table" class="table table-striped">
            <thead>
                <tr>
                    <th>ລຳດັບ</th>
                    <th>Order ID</th>
                    <th>ຍອດລວມ</th>
                    <th>ສະຖານະ</th>
                    <th>ວັນທີ</th>
                    <th>ປະເພດຂົນສົ່ງ-ທີ່ຢູ່ສາຂາ-ເບີໂທ</th>
                    <th>ກວດສອບອໍເດີ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7">ບໍ່ມີອໍເດີທີ່ຈ່າຍແລ້ວ</td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $index => $order): ?>
                <tr>
                    <td><?php echo htmlspecialchars($index + 1 + ($current_page - 1) * $per_page); ?></td>
                    <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                    <td><?php echo number_format($order['order_total']); ?> ₭</td>
                    <td><?php echo isset($status_map[$order['order_status']])
                                    ? $status_map[$order['order_status']]
                                    : htmlspecialchars($order['order_status']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                    <td>
                        <?php
                                echo htmlspecialchars($order['express']) . ' - ' .
                                    htmlspecialchars($order['branch_address']) . ' - ' .
                                    htmlspecialchars($order['phone']);
                                ?>
                    </td>
                    <td>
                        <button class="modalBtn btn btn-primary"
                            data-modal-id="editModal<?php echo $order['order_id']; ?>">ເບິ່ງລາຍລະອຽດ</button>
                        <button class="btn btn-info" id="viewImageBtn">ເບິ່ງຮູບໃບບິນ</button>
                        <button class="btn btn-print modalBtn"
                            data-modal-id="receiptContent<?php echo $order['order_id']; ?>" title="ພິມໃບບິນ">
                            <img src="../assets/icon/printer.png" alt="Print">
                        </button>

                    </td>

                </tr>
                <!-- Modal for viewing image -->
                <div class="modal" id="receiptContent<?php echo $order['order_id']; ?>">
                    <div class="modal-content" id="receiptContentBox<?php echo $order['order_id']; ?>">
                        <span class="close">&times;</span>
                        <h5 class="modal-title"></h5>
                        <hr>
                        <div class="form-group">
                            <label>Order ID:</label>
                            <div class="form-print"><?php echo htmlspecialchars($order['order_id']); ?></div>
                        </div>
                        <div class="form-group product-list-horizontal">
                                <?php foreach ($order_items_map[$order['order_id']] as $item): ?>
                                <div class="item-img">
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
                            
                        <div class="form-group">
                            <span class="form-label">ວັນທີ:</span>
                            <div class="form-print"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>ປະເພດຂົນສົ່ງ-ທີ່ຢູ່ສາຂາ-ເບີໂທ:</label>
                            <div class="form-print">
                                <?php echo htmlspecialchars($order['express']) . ' - ' . htmlspecialchars($order['branch_address']) . ' - ' . htmlspecialchars($order['phone']); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>ຍອດລວມ:</label>
                            <div class="form-print"><?php echo number_format($order['order_total']); ?> ₭</div>
                        </div>
                        <button type="button" class="btn btn-print"
                            onclick="printOrder(<?php echo $order['order_id']; ?>)">
                            <img src="../assets/icon/printer.png" alt="Print">
                        </button>

                        </form>
                    </div>
                </div>

                <!-- Modal Edit -->
                <div class="modal" id="editModal<?php echo $order['order_id']; ?>">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h5 class="modal-title">ກວດສອບອໍເດີ</h5>
                        <hr>
                        <form id="editForm<?php echo $order['order_id']; ?>" enctype="multipart/form-data">
                            <input type="hidden" name="order_id"
                                value="<?php echo htmlspecialchars($order['order_id']); ?>">
                            <div class="form-group">
                                <label>order Id:</label>
                                <input type="text" class="form-control"
                                    value="<?php echo htmlspecialchars($order['order_id']); ?>" readonly>
                            </div>
                            <div class="form-group product-list-horizontal">
                                <?php foreach ($order_items_map[$order['order_id']] as $item): ?>
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

                        </form>
                    </div>
                </div>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div id="imageModal" class="modal"
        style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.7);align-items:center;justify-content:center;">
        <span class="close"
            style="position:absolute;top:30px;right:40px;font-size:40px;color:#fff;cursor:pointer;">&times;</span>
        <img id="modalImage" src="../<?= htmlspecialchars($order['image']) ?>" alt="ຮູບຝາກສົ່ງ"
            style="max-width:90vw;max-height:80vh;display:block;margin:auto;">
    </div>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dom-to-image-more@2.9.0/dist/dom-to-image-more.min.js"></script>
<script src="../assets/js/lazy_loading.js"></script>
<script src="../assets/js/togglemenu.js"></script>
<script src="../assets/js/dropdown.js"></script>
<script src="../assets/js/profile.js"></script>
<script src="../assets/js/modal_admin.js"></script>
<script src="../assets/js/view_img.js"></script>
<script src="../assets/js/print_sell.js"></script>

</html>