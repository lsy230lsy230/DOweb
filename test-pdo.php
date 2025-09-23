<?php
echo "<h2>PDO Test</h2>";

echo "<h3>Available PDO Drivers:</h3>";
print_r(PDO::getAvailableDrivers());

echo "<h3>Extension Check:</h3>";
echo "PDO extension loaded: " . (extension_loaded('pdo') ? '✓ Yes' : '✗ No') . "<br>";
echo "PDO_PGSQL extension loaded: " . (extension_loaded('pdo_pgsql') ? '✓ Yes' : '✗ No') . "<br>";

echo "<h3>Environment Variables:</h3>";
echo "DATABASE_URL: " . ($_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?? 'Not set') . "<br>";

if (extension_loaded('pdo_pgsql')) {
    echo "<h3>Testing PostgreSQL Connection:</h3>";
    try {
        $database_url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if (!$database_url) {
            echo "DATABASE_URL not set";
        } else {
            $pdo = new PDO($database_url);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "✓ PostgreSQL connection successful!";
        }
    } catch (PDOException $e) {
        echo "✗ Connection failed: " . $e->getMessage();
    }
} else {
    echo "PDO_PGSQL extension not loaded";
}
?>