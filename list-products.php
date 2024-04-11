<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function fetchAndAttachProductData($pdo, &$products, $queryTemplate, $dataKey) {
    if (empty($products)) return;

    $productIds = array_column($products, 'product_id');
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    $query = sprintf($queryTemplate, $placeholders);
    $stmt = $pdo->prepare($query);
    $stmt->execute($productIds);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as &$product) {
        $filteredData = array_filter($data, function ($item) use ($product) {
            return $item['product_id'] == $product['product_id'];
        });

        if ($dataKey == 'images') {
            // If data key is images, extract URLs
            $product[$dataKey] = array_map(function($item) {
                return $item['url'] ?? 'Unknown'; // Fallback to 'Unknown' if URL is absent
            }, $filteredData);
        } elseif ($dataKey == 'versions') {
            // For versions, create an array of version names and prices
            $product[$dataKey] = array_map(function($item) {
                return [
                    'version' => $item['version'] ?? 'Unknown', // Fallback to 'Unknown' if version is absent
                    'price' => $item['price'] ?? '0.00' // Fallback to '0.00' if price is absent
                ];
            }, $filteredData);
        } else {
            // For other data keys, use a generic approach
            $keyToExtract = 'name'; // This is a placeholder for other types of data, assuming they just need a name
            $product[$dataKey] = array_map(function($item) use ($keyToExtract) {
                return $item[$keyToExtract] ?? 'Unknown'; // Fallback
            }, $filteredData);
        }
    }
    unset($product); // Break the reference with the last element
}

// Base query
$baseQuery = "SELECT p.* FROM products p";


$whereConditions = [];
$joins = [];
$params = [];
$orderBy = " ORDER BY p.product_id"; // Default order

// filters and conditions
if (isset($_GET['category_ids'])) {
    $categoryIds = explode(',', $_GET['category_ids']);
    $joins[] = "JOIN product_categories pc ON p.product_id = pc.product_id AND pc.categories_id IN (" . implode(',', array_fill(0, count($categoryIds), '?')) . ")";
    $params = array_merge($params, $categoryIds);
}

/// Filtering by Genre
if (isset($_GET['genre_ids'])) {
    $genreIds = explode(',', $_GET['genre_ids']);
    $joins[] = "JOIN product_genres pg ON p.product_id = pg.product_id AND pg.genres_id IN (" . implode(',', array_fill(0, count($genreIds), '?')) . ")";
    $params = array_merge($params, $genreIds);
}

// Filtering by Language
if (isset($_GET['language_ids'])) {
    $languageIds = explode(',', $_GET['language_ids']);
    $joins[] = "JOIN product_languages pl ON p.product_id = pl.product_id AND pl.languages_id IN (" . implode(',', array_fill(0, count($languageIds), '?')) . ")";
    $params = array_merge($params, $languageIds);
}

// Filtering by Subtitle
if (isset($_GET['subtitle_ids'])) {
    $subtitleIds = explode(',', $_GET['subtitle_ids']);
    $joins[] = "JOIN product_subtitles ps ON p.product_id = ps.product_id AND ps.subtitles_id IN (" . implode(',', array_fill(0, count($subtitleIds), '?')) . ")";
    $params = array_merge($params, $subtitleIds);
}

// Filtering by Developer
if (isset($_GET['developer_ids'])) {
    $developerIds = explode(',', $_GET['developer_ids']);
    $joins[] = "JOIN developers d ON p.developer_id = d.developer_id AND d.developer_id IN (" . implode(',', array_fill(0, count($developerIds), '?')) . ")";
    $params = array_merge($params, $developerIds);
}

// Filtering by Tag
if (isset($_GET['tag_ids'])) {
    $tagIds = explode(',', $_GET['tag_ids']);
    $joins[] = "JOIN product_tags pt ON p.product_id = pt.product_id AND pt.tags_id IN (" . implode(',', array_fill(0, count($tagIds), '?')) . ")";
    $params = array_merge($params, $tagIds);
}

// Price range filtering
if (isset($_GET['minPrice'], $_GET['maxPrice'])) {
    $joins[] = "JOIN versions v ON p.product_id = v.product_id";
    $whereConditions[] = "v.price BETWEEN ? AND ?";
    $params[] = $_GET['minPrice'];
    $params[] = $_GET['maxPrice'];
}


// Sorting
if (isset($_GET['sort'])) {
    $sortFields = ['name_asc' => 'p.name ASC', 'name_desc' => 'p.name DESC', 'price_high_to_low' => 'p.price DESC', 'price_low_to_high' => 'p.price ASC'];
    if (array_key_exists($_GET['sort'], $sortFields)) {
        $orderBy = " ORDER BY " . $sortFields[$_GET['sort']];
    }
}

// Search functionality
if (isset($_GET['search'])) {
    $searchTerm = "%" . $_GET['search'] . "%";
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Append joins, where conditions, and order by to the base query
if (!empty($joins)) $baseQuery .= ' ' . implode(' ', $joins);
if (!empty($whereConditions)) $baseQuery .= ' WHERE ' . implode(' AND ', $whereConditions);
$baseQuery .= $orderBy;

// Pagination
if (isset($_GET['page'], $_GET['limit'])) {
    $baseQuery .= " LIMIT ?, ?";
    $params[] = ($_GET['page'] - 1) * $_GET['limit'];
    $params[] = $_GET['limit'];
}

$stmt = $pdo->prepare($baseQuery);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($products)) {
    $productIds = array_column($products, 'product_id');
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';

    // Categories
   fetchAndAttachProductData($pdo, $products, "SELECT pc.product_id, c.name FROM product_categories pc JOIN categories c ON pc.categories_id = c.category_id WHERE pc.product_id IN (%s)", 'categories');

// Genres
fetchAndAttachProductData($pdo, $products, "SELECT pg.product_id, g.name FROM product_genres pg JOIN genres g ON pg.genres_id = g.genre_id WHERE pg.product_id IN (%s)", 'genres');

// Languages
fetchAndAttachProductData($pdo, $products, "SELECT pl.product_id, l.name FROM product_languages pl JOIN languages l ON pl.languages_id = l.language_id WHERE pl.product_id IN (%s)", 'languages');

// Subtitles
fetchAndAttachProductData($pdo, $products, "SELECT ps.product_id, s.name FROM product_subtitles ps JOIN subtitles s ON ps.subtitles_id = s.subtitle_id WHERE ps.product_id IN (%s)", 'subtitles');

// Tags
fetchAndAttachProductData($pdo, $products, "SELECT pt.product_id, t.name FROM product_tags pt JOIN tags t ON pt.tags_id = t.tag_id WHERE pt.product_id IN (%s)", 'tags');



  // Images
fetchAndAttachProductData($pdo, $products, "SELECT pi.product_id, pi.url FROM ProductImages pi WHERE pi.product_id IN (%s)", 'images');

// Versions
fetchAndAttachProductData($pdo, $products, "SELECT v.product_id, v.version, v.price, v.income FROM versions v WHERE v.product_id IN (%s)", 'versions');

}

echo json_encode(['products' => $products]);
