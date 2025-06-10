<?php

// Utwórz plik check_extensions.php w katalogu public i uruchom w przeglądarce

echo "<h2>Dostępne rozszerzenia PHP:</h2>";
echo "<ul>";
foreach (get_loaded_extensions() as $extension) {
    echo "<li>$extension</li>";
}
echo "</ul>";

echo "<h3>Sprawdzanie konkretnych rozszerzeń:</h3>";
echo "ZipArchive: " . (class_exists('ZipArchive') ? '✅ Dostępne' : '❌ Niedostępne') . "<br>";
echo "SimpleXML: " . (extension_loaded('simplexml') ? '✅ Dostępne' : '❌ Niedostępne') . "<br>";
echo "XMLReader: " . (extension_loaded('xmlreader') ? '✅ Dostępne' : '❌ Niedostępne') . "<br>";
echo "GD: " . (extension_loaded('gd') ? '✅ Dostępne' : '❌ Niedostępne') . "<br>";

echo "<h3>Informacje o PHP:</h3>";
phpinfo();