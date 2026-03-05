<?php
header('Content-Type: application/json');
include 'config.php';

// SQL query para kumuha ng mga balita
$sql = "SELECT * FROM news_corner  ORDER BY date_time_published DESC";
$result = $conn->query($sql);

$news = array();

if ($result->num_rows > 0) {
  // Mag-output ng data ng bawat row
  while($row = $result->fetch_assoc()) {
    $news[] = $row;
  }
} else {
  echo "0 results";
}

// I-close ang koneksyon
$conn->close();

// Ibalik ang resulta bilang JSON
header('Content-Type: application/json');
echo json_encode($news);
?>