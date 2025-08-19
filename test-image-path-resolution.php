<?php
/**
 * Standalone test for image path resolution logic
 */

echo "=== Image Path Resolution Test ===\n";

// Simulate the ZIP extraction directory structure
$tempDir = '/tmp/test-extraction';
$subdirPath = $tempDir . '/immonex-oi-demo-data-generic';

// Create test directory structure
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}
if (!is_dir($subdirPath)) {
    mkdir($subdirPath, 0755, true);
}

// Create a test image file in the subdirectory
$testImageName = '32_Fotolia_83465835_S.jpg';
$testImagePath = $subdirPath . '/' . $testImageName;
file_put_contents($testImagePath, 'fake image content');

echo "Created test structure:\n";
echo "- Temp dir: $tempDir\n";
echo "- Subdir: $subdirPath\n";
echo "- Test image: $testImagePath\n\n";

// Test the path resolution logic (simulating our fixed code)
function findImageInSubdirectories(string $baseDir, string $filename): ?string
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === $filename) {
            echo "Found image file: " . $file->getPathname() . "\n";
            return $file->getPathname();
        }
    }

    return null;
}

// Test 1: Direct path (should fail)
$directPath = $tempDir . '/' . $testImageName;
echo "Test 1 - Direct path: $directPath\n";
if (file_exists($directPath)) {
    echo "✓ Found at direct path\n";
} else {
    echo "✗ Not found at direct path (expected)\n";
}

// Test 2: Subdirectory search (should succeed)
echo "\nTest 2 - Subdirectory search:\n";
$foundPath = findImageInSubdirectories($tempDir, $testImageName);
if ($foundPath) {
    echo "✓ Found image in subdirectory: $foundPath\n";
} else {
    echo "✗ Image not found in subdirectories\n";
}

// Test 3: Verify the found path is correct
if ($foundPath && $foundPath === $testImagePath) {
    echo "✓ Path resolution working correctly!\n";
} else {
    echo "✗ Path resolution failed\n";
}

// Cleanup
unlink($testImagePath);
rmdir($subdirPath);
rmdir($tempDir);

echo "\n=== Test completed ===\n";
echo "The image path resolution fix should work correctly for ZIP files with subdirectory structure.\n";
