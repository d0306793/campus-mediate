<?php
// coming-soon.php

$feature = isset($_GET['feature']) ? htmlspecialchars($_GET['feature']) : 'This feature';
$eta = isset($_GET['eta']) ? htmlspecialchars($_GET['eta']) : 'Coming Soon';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coming Soon - <?= $feature ?></title>
    <style>
        :root {
            --primary-purple: #6a0dad;
            --purple-light: #9c27b0;
            --purple-dark: #4b0082;
            --secondary-blue: #2196f3;
            --blue-light: #64b5f6;
            --accent-green: #4caf50;
            --text-dark: #2d3748;
            --text-light: #f8f9fa;
            --gray-light: #edf2f7;
            --gray-medium: #e2e8f0;
            --error-red: #dc3545;
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--gray-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .coming-soon-container {
            text-align: center;
            padding: 2rem 3rem;
            background-color: white;
            box-shadow: var(--shadow-md);
            border-radius: 8px;
            max-width: 520px;
            animation: fadeIn 1s ease-in-out;
        }

        .coming-soon-container h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--purple-dark);
        }

        .coming-soon-container p {
            font-size: 1.1rem;
            color: var(--text-dark);
        }

        .coming-soon-container .eta {
            margin-top: 1rem;
            font-size: 1rem;
            color: var(--secondary-blue);
            font-weight: bold;
            animation: pulse 1.8s infinite;
        }

        .coming-soon-container .back-link {
            margin-top: 1.5rem;
            display: inline-block;
            text-decoration: none;
            padding: 0.5rem 1.2rem;
            background-color: var(--primary-purple);
            color: white;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .coming-soon-container .back-link:hover {
            background-color: var(--purple-dark);
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="coming-soon-container">
    <h1>🚧 <?= $feature ?> is Coming Soon!</h1>
    <p>We’re working hard to bring this feature to life. Please check back later.</p>
    <div class="eta">Estimated Launch: <?= $eta ?></div>
    <a class="back-link" href="javascript:history.back()">← Go Back</a>
</div>

</body>
</html>
