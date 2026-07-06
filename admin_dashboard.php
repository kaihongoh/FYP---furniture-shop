<?php
session_start();
require_once(__DIR__ . "/../includes/config.php");


if(!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: admin_login.php");
    exit();
}


// top card
$sql_stats = "SELECT 
    COUNT(*) as total_orders, 
    SUM(CASE WHEN Order_Status = 'Completed' AND DATE(Updated_Date) = CURDATE() THEN Total_Amount ELSE 0 END) as today_sales,
    SUM(CASE WHEN Order_Status = 'Completed' AND MONTH(Updated_Date) = MONTH(CURDATE()) AND YEAR(Updated_Date) = YEAR(CURDATE()) THEN Total_Amount ELSE 0 END) as monthly_revenue,
    SUM(CASE WHEN Order_Status IN ('Partially_Refunded', 'Fully_Refunded') THEN 1 ELSE 0 END) as problem_orders
FROM orders";

$stats = $conn->query($sql_stats)->fetch_assoc();


$monthly_revenue = $stats['monthly_revenue'] ?? 0;  
$today_sales   = $stats['today_sales'] ?? 0;
$total_orders  = $stats['total_orders'] ?? 0;
$problem_orders       = $stats['problem_orders'] ?? 0;

// If today_sales is NULL, set it to 0
if ($today_sales === null) {
    $today_sales = 0;
}

//order distribution(pie chart)
$status_label=[];
$status_data=[];

$res= $conn->query("SELECT Order_Status, COUNT(*) as count FROM orders GROUP BY Order_Status");
while ($row = $res->fetch_assoc()) {
    $status_label[] = $row['Order_Status'];
    $status_data[] = $row['count'];
}

//revenue growth (day,month,year)
//day (show when the order
$daily_label=[];
$daily_data=[];
$res = $conn->query("SELECT DATE_FORMAT(Updated_Date, '%d-%b') as date, SUM(Total_Amount) as daily_total 
                     FROM orders WHERE Order_Status = 'Completed' 
                     GROUP BY DATE(Updated_Date) 
                     ORDER BY (Updated_Date) ASC");
while ($row = $res->fetch_assoc()) {
    $daily_label[] = $row['date'];
    $daily_data[] = $row['daily_total'];
}

//monthly
$monthly_label=[];  
$monthly_data=[];
$res = $conn->query("SELECT DATE_FORMAT(Updated_Date, '%b %Y') as month, SUM(Total_Amount) as monthly_total 
                     FROM orders WHERE Order_Status = 'Completed' 
                     GROUP BY YEAR(Updated_Date), MONTH(Updated_Date) 
                     ORDER BY YEAR(Updated_Date), MONTH(Updated_Date)");
while ($row = $res->fetch_assoc()) {
    $monthly_label[] = $row['month'];
    $monthly_data[] = $row['monthly_total'];
}

//year
$yearly_label = []; 
$yearly_data = []; 
$res = $conn->query("SELECT YEAR(Updated_Date) as year, SUM(Total_Amount) as year_total 
                     FROM orders WHERE Order_Status = 'Completed' 
                     GROUP BY YEAR(Updated_Date) 
                     ORDER BY YEAR(Updated_Date)");
while ($row = $res->fetch_assoc()) {
    $yearly_label[] = $row['year'];
    $yearly_data[] = $row['year_total'];
}

//card
$cards = [
    ['title' => "Today's Sales", 'value' => 'RM ' . number_format($today_sales, 2), 'icon' => 'lightning-charge-fill', 'color' => 'info'],
    ['title' => "Monthly Balance", 'value' => 'RM ' . number_format($monthly_revenue, 2), 'icon' => 'graph-up', 'color' => 'success'],
    ['title' => "Total Orders",  'value' => $total_orders, 'icon' => 'cart-fill', 'color' => 'primary'],
    ['title' => "Issues",'value' => $problem_orders, 'icon' => 'exclamation-triangle', 'color' => 'danger']
];

//top selling product
$top_product=[];
$top_sales=[];


//even change the product name, the product still not change because get from order items (is record/history)
$res=$conn->query("SELECT order_items.Product_Name, SUM(order_items.Quantity) as total 
FROM order_items    
JOIN orders ON order_items.Order_ID = orders.Order_ID
WHERE orders.Order_Status = 'Completed'
AND orders.Updated_Date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY order_items.Product_Name
ORDER BY total DESC 
LIMIT 5");

while ($row = $res->fetch_assoc()) {
    $top_product[]=$row['Product_Name'];
    $top_sales[]=$row['total'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - HomeNest</title>
    <!-- Bootstrap CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <!--  add bootstrap icon CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="../css/admin_dashboard.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    

</head>
<body>
        <?php include __DIR__ . "/../admin/admin_sidebar.php"; ?>

    <main class="main-content p-4">
        <h2 class="fw-bold mb-4">Dashboard Overview</h2>
        
        <!--top card-->
        <div class="row g-4 mb-4">
            <?php foreach($cards as $c): ?>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm p-3">
                    <div class="text-<?php echo $c['color']; ?> fs-3 mb-2">
                        <i class="bi bi-<?php echo $c['icon']; ?>"></i>
                    </div>
                    <h6 class="text-muted small"><?php echo $c['title']; ?></h6>
                    <h4 class="fw-bold"><?php echo $c['value']; ?></h4>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!--chart-->    
        <div class="row g-4">
            <!--revenue growth-->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                       <h5 class="fw-bold mb-3">Revenue Growth</h5> 
                    <select id="revenue_filter" class="form-select w-auto">
                        <option value="daily">Daily</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                    </div>
                    <canvas id="salesChart" height="300"></canvas>
                </div>
            </div>

            <!--order distribution-->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4">
                    <h5 class="fw-bold mb-3">Order Status Distribution</h5>
                    <canvas id="statusChart" height="300"></canvas>
                </div>
            </div>
        </div>

            <!--top selling product-->
            <div class="row g-4 mt-2">
                <div class="col-md-12">
                    <div class="card shadow-sm p-4 border-0">
                        <h5 class="fw-bold mb-3">Top Selling Product (Last 30 days)</h5>
                        <canvas id="top_product_chart" height="300"></canvas>
                    </div>
                </div>
            </div>
    </main>




<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
//revenue line chart
const dataSets= {
    daily: {
        labels: <?php echo json_encode($daily_label); ?>,
        data: <?php echo json_encode($daily_data); ?>   
    },
    monthly: {
        labels: <?php echo json_encode($monthly_label); ?>,
        data: <?php echo json_encode($monthly_data); ?>
    },
    yearly: {
        labels: <?php echo json_encode($yearly_label); ?>,
        data: <?php echo json_encode($yearly_data); ?>
    }
}; 

let currentType="daily";
const ctx=document.getElementById('salesChart').getContext('2d');

let chart=new Chart(ctx, {
    type: 'line',
    data: {
        labels: dataSets[currentType].labels,
        datasets: [{
            label:'revenue',
            data: dataSets[currentType].data,
            borderColor:'#0058a3',
            fill:true,
            tension:0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {display:false}
        },
        animation: {
            duration:800
        }
    }
});

//change filter
document.getElementById('revenue_filter').addEventListener('change', function() {
    currentType=this.value;

    chart.data.labels = dataSets[currentType].labels;
    chart.data.datasets[0].data = dataSets[currentType].data;

    chart.update();
});

//product selling bar chart
new Chart(document.getElementById('top_product_chart'), {
    type:'bar',
    data: {
        labels: <?php echo json_encode($top_product); ?>,
        datasets: [{
            label:'units sold',
            data:<?php echo json_encode($top_sales); ?>
        }]
    },
    options: {
        indexAxis:'y',
        plugins: {
            datalabels: {
                anchor: 'end',
                align: 'right',
                color: '#000',
                font: {
                    weight: 'bold'
                },
                formatter: function(value) {
                    return value;
                }
            }
        }
    },
    plugins: [ChartDataLabels]
});
        // order distribution pie chart
        const statusLabel=<?php echo json_encode($status_label); ?>;
        const statusData=<?php echo json_encode($status_data); ?>;

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabel,
                datasets: [{ data: statusData, 
                backgroundColor: ['#ffc107', '#0d6efd', '#198754', '#dc3545', '#6f42c1','#fd7e14','#20c997','#4a3270'] }]
            },
            options: {
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 12
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    </script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>          
    </body>
</html>