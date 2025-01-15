<?php
// Directory configurations
$uploadDir = __DIR__ . '/uploads/';
$resizedDir = __DIR__ . '/resized/';
$zipFile = __DIR__ . '/resized_images.zip'; // Path for the ZIP file

// Create directories if they don't exist
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($resizedDir)) mkdir($resizedDir, 0755, true);

$zipCreated = false; // Flag to track if the ZIP is created
$resizedImages = []; // Array to hold resized image paths

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['images'])) {
    $width = intval($_POST['width']);
    $height = intval($_POST['height']);

    if ($width > 0 && $height > 0) {
        $files = $_FILES['images'];
        $totalFiles = count($files['name']);

        // Check if the number of uploaded files exceeds 10
        if ($totalFiles > 10) {
            echo "<script>alert('You can upload a maximum of 10 images at a time.');</script>";
            exit;
        }

        // Clear previous resized images and ZIP file
        array_map('unlink', glob($resizedDir . "*"));
        if (file_exists($zipFile)) unlink($zipFile);

        for ($i = 0; $i < $totalFiles; $i++) {
            if ($files['error'][$i] == 0) {
                $originalName = pathinfo($files['name'][$i], PATHINFO_FILENAME); // Get original name without extension
                $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);  // Get file extension
                $imagePath = $uploadDir . basename($files['name'][$i]);

                // Move uploaded file to uploads directory
                if (move_uploaded_file($files['tmp_name'][$i], $imagePath)) {
                    $imageDetails = getimagesize($imagePath);
                    if ($imageDetails) {
                        $sourceImage = null;

                        // Use only the original filename (no extension in name)
                        $resizedImageName = $originalName;
                        $resizedImagePath = $resizedDir . $resizedImageName;

                        // Load the image based on its MIME type
                        switch ($imageDetails['mime']) {
                            case 'image/jpeg':
                                $sourceImage = imagecreatefromjpeg($imagePath);
                                break;
                            case 'image/png':
                                $sourceImage = imagecreatefrompng($imagePath);
                                break;
                            case 'image/gif':
                                $sourceImage = imagecreatefromgif($imagePath);
                                break;
                            case 'image/webp':
                                $sourceImage = imagecreatefromwebp($imagePath);
                                break;
                            default:
                                continue 2;
                        }

                        $resizedImage = imagecreatetruecolor($width, $height);

                        // Retain transparency for PNG and WebP
                        if ($imageDetails['mime'] == 'image/png' || $imageDetails['mime'] == 'image/webp') {
                            imagealphablending($resizedImage, false);
                            imagesavealpha($resizedImage, true);
                            $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
                            imagefill($resizedImage, 0, 0, $transparent);
                        }

                        // Resize image
                        imagecopyresampled(
                            $resizedImage,
                            $sourceImage,
                            0, 0, 0, 0,
                            $width, $height,
                            imagesx($sourceImage),
                            imagesy($sourceImage)
                        );

                        // Save resized image
                        switch ($imageDetails['mime']) {
                            case 'image/jpeg':
                                imagejpeg($resizedImage, $resizedImagePath . '.jpg'); // Save as .jpg
                                break;
                            case 'image/png':
                                imagepng($resizedImage, $resizedImagePath . '.png'); // Save as .png
                                break;
                            case 'image/gif':
                                imagegif($resizedImage, $resizedImagePath . '.gif'); // Save as .gif
                                break;
                            case 'image/webp':
                                imagewebp($resizedImage, $resizedImagePath . '.webp'); // Save as .webp
                                break;
                        }

                        imagedestroy($sourceImage);
                        imagedestroy($resizedImage);

                        $resizedImages[] = $resizedImagePath . '.' . $extension; // Store the full path with the correct extension
                    }
                }
            }
        }

        // Create ZIP file with all resized images
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            foreach ($resizedImages as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            $zipCreated = true; // Set ZIP created flag to true
        }
    } else {
        echo "<script>alert('Please provide valid dimensions.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Resizer</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: #f9f9f9;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #007BFF;
            margin-top: 30px;
            margin-bottom: 20px;
        }
        form {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        form label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #555;
        }
        form input[type="file"],
        form input[type="number"] {
            width: calc(100% - 20px);
            padding: 8px 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            display: block;
            width: 100%;
            padding: 12px;
            background: #007BFF;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        button:hover:not(:disabled) {
            background: #0056b3;
        }
        .gallery {
            display: grid;
            grid-template-columns: repeat(5, 1fr); /* 5 columns per row */
            gap: 20px;
            margin: 20px auto;
            padding: 0 15px;
            max-width: 1000px;
        }
        .gallery-item {
            text-align: center;
        }
        .gallery img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .gallery-item p {
            margin: 8px 0 0;
            font-size: 14px;
            color: #555;
            word-wrap: break-word;
        }
        .download-btn {
            max-width: 500px;
            margin: 0 auto 20px auto;
            text-align: center;
        }
    </style>
    <script>
        function toggleDownloadButton(enabled) {
            const button = document.getElementById('downloadButton');
            button.disabled = !enabled;
        }
    </script>
</head>
<body onload="toggleDownloadButton(<?= $zipCreated ? 'true' : 'false' ?>)">

    <h1>Image Resizer</h1>

    <form method="POST" enctype="multipart/form-data">
        <label for="images">Select Images (max 10):</label>
        <input type="file" name="images[]" id="images" multiple required>
        <label for="width">Width (px):</label>
        <input type="number" name="width" id="width" required>
        <label for="height">Height (px):</label>
        <input type="number" name="height" id="height" required>
        <button type="submit">Resize Images</button>
    </form>

    <div class="download-btn">
        <button id="downloadButton" onclick="window.location.href='resized_images.zip'" disabled>Download All Resized Images</button>
    </div>

    <?php if (!empty($resizedImages)): ?>
        <div class="gallery">
            <?php foreach ($resizedImages as $image): ?>
                <div class="gallery-item">
                    <img src="<?= 'resized/' . basename($image) ?>" alt="<?= basename($image) ?>">
                    <p><?= basename($image) ?></p> <!-- Display the image name -->
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</body>
</html>