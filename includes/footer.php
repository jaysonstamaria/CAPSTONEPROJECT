</main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> CarsRUs. All rights reserved.</p>
        <p style="font-size: 0.8em;"><a href="admin_login.php">Admin Portal</a></p>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>
<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>