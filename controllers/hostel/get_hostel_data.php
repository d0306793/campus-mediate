<?php
// We'll need to query the rooms table to get the available room counts for 
// each hostel to display on the homepage.


header('Content-Type: application/json');

include '../../config/config.php';

$stmt = $conn->prepare("
    SELECT
        h.id,
        h.name,
        h.location,
        h.description,
        h.amenities,
        h.image_path,
        JSON_ARRAYAGG(
            JSON_OBJECT(
                'room_type', r.room_type,
                'price', r.price_per_semester
            )
        ) AS room_prices,
        SUM(CASE WHEN r.status = 'Available' THEN r.quantity ELSE 0 END) AS available_rooms_count
    FROM hostels h
    LEFT JOIN rooms r ON h.id = r.hostel_id
    WHERE h.status = 'Active'
    GROUP BY h.id
");
$stmt->execute();
$result = $stmt->get_result();

$hostels = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['amenities'] = json_decode($row['amenities'] ?? '[]', true);
        $row['room_prices'] = json_decode($row['room_prices'] ?? '[]', true);
        $hostels[] = $row;
    }
}

$conn->close();

echo json_encode($hostels);
?>