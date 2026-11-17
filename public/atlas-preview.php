<?php
$atlas = '/assets/icons/atlas/banner-icons.png';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Foxcraft - Atlas Preview</title>

<style>
    body {
        margin: 0;
        padding: 30px;
        background: #111;
        color: white;
        font-family: sans-serif;
    }

    h2 {
        margin-bottom: 20px;
    }

    .atlas {
        display: grid;
        grid-template-columns: repeat(16, 48px);
        width: max-content;
        background-color: #292929;
    }

    .cell {
        width: 48px;
        height: 48px;
        position: relative;
        border: 1px solid #ffffff30;
        box-sizing: border-box;
        background-image: url('<?= $atlas ?>');
        background-size: 768px 768px;
        background-repeat: no-repeat;
    }

    .cell span {
        position: absolute;
        top: 1px;
        left: 2px;
        font-size: 9px;
        color: yellow;
        text-shadow: 1px 1px black;
    }
</style>
</head>

<body>

<h2>Atlas de iconos Foxcraft</h2>

<div class="atlas">
    <?php for ($y = 0; $y < 16; $y++): ?>
        <?php for ($x = 0; $x < 16; $x++): ?>
            <div
                class="cell"
                style="background-position: -<?= $x * 48 ?>px -<?= $y * 48 ?>px"
            >
                <span><?= $x ?>,<?= $y ?></span>
            </div>
        <?php endfor; ?>
    <?php endfor; ?>
</div>

</body>
</html>
