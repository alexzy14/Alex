<?php
include_once '../server/config/check_user.php'; 
include_once '../server/config/request_url.php';

date_default_timezone_set('Asia/Vientiane');
// กำหนดเดือนและปีปัจจุบัน
$currentMonth = date('m');
$currentYear = date('Y');
// นับรายได้รายวันของเดือนปัจจุบัน
$revenueSql = "
    SELECT DATE(payment_date) AS date, SUM(payment_amount) AS revenue
    FROM payment
    WHERE payment_status = 'success'
      AND MONTH(payment_date) = :month
      AND YEAR(payment_date) = :year
    GROUP BY DATE(payment_date)
    ORDER BY date
";
// เตรียมข้อมูลสำหรับการดึงรายได้หรือกราฟ
$revenueStmt = $conn->prepare($revenueSql);
$revenueStmt->bindParam(':month', $currentMonth);
$revenueStmt->bindParam(':year', $currentYear);
$revenueStmt->execute();
$revenueDataPoints = array();

while ($row = $revenueStmt->fetch(PDO::FETCH_ASSOC)) {
    $revenueDataPoints[] = array(
        "y" => (float)$row['revenue'],
        "label" => $row['date']
    );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php include_once "../includes/icon.php"; ?>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/home.css">
    <link rel="stylesheet" href="../assets/css/graphs.css">
    <link rel="stylesheet" href="../assets/css/table.css">
</head>
<body>
    <?php include_once "../includes/header_admin.php"; ?>
    <?php include_once "../template/admin_home.php"; ?>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="../assets/js/togglemenu.js"></script>
<script src="../assets/js/dropdown.js"></script>
<script src="../assets/js/profile.js"></script>
<script>
        window.onload = function() {    
            var revenueChart = new CanvasJS.Chart("chartContainer", {
                animationEnabled: true,
                title: {
                    text: "ລາຍໄດ້ລາຍວັນຂອງເດືອນນີ້", 
                    fontFamily: 'Noto Serif Lao',
                    fontWeight: 600
                },
                axisY: {
                    title: "ລາຍໄດ້",
                    includeZero: true,
                    fontFamily: 'Noto Serif Lao',
                    fontWeight: 600
                },
                axisX: {
                    title: "ວັນ", 
                    fontFamily: 'Noto Serif Lao',
                    fontWeight: 600
                },
                data: [{
                    type: "column",
                    yValueFormatString: "#,##0.## ກີບ",
                    indexLabel: "{y}",
                    indexLabelFontColor: "#000000FF",
                    fontFamily: 'Noto Serif Lao',
                    fontWeight: 600,
                    dataPoints: <?php echo json_encode($revenueDataPoints, JSON_NUMERIC_CHECK); ?>
                }]
            });

            revenueChart.render();
        }
    </script>
</html> 