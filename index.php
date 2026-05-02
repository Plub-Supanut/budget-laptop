<?php

// Database configuration (using SQLite for simplicity)
define('DB_PATH', dirname(__FILE__) . '/laptops.db');

// Initialize database from JSON if it doesn't exist or JSON is newer
function initDatabase() {
    $dbPath = DB_PATH;
    $jsonPath = dirname(__FILE__) . '/laptops.json';
    
    $shouldInit = !file_exists($dbPath);
    if (file_exists($dbPath) && file_exists($jsonPath)) {
        // If JSON is newer than DB, we should refresh the data
        if (filemtime($jsonPath) > filemtime($dbPath)) {
            $shouldInit = true;
        }
    }

    if ($shouldInit) {
        $db = new SQLite3($dbPath);
        
        // Recreate table
        $db->exec("DROP TABLE IF EXISTS laptops");
        $db->exec("CREATE TABLE laptops (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            brand TEXT NOT NULL,
            price_thb INTEGER NOT NULL,
            cpu_model TEXT NOT NULL,
            cpu_cores INTEGER NOT NULL,
            cpu_ghz REAL NOT NULL,
            ram_gb INTEGER NOT NULL,
            ram_type TEXT NOT NULL,
            storage_gb INTEGER NOT NULL,
            storage_type TEXT NOT NULL,
            gpu_model TEXT NOT NULL,
            gpu_vram_gb INTEGER NOT NULL,
            llm_performance_score INTEGER NOT NULL,
            shop_url TEXT,
            image_url TEXT,
            last_updated DATE NOT NULL
        )");
        
        if (file_exists($jsonPath)) {
            $laptopsData = json_decode(file_get_contents($jsonPath), true);
            if ($laptopsData) {
                $stmt = $db->prepare("INSERT INTO laptops (name, brand, price_thb, cpu_model, cpu_cores, cpu_ghz, ram_gb, ram_type, storage_gb, storage_type, gpu_model, gpu_vram_gb, llm_performance_score, shop_url, image_url, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($laptopsData as $laptop) {
                    $stmt->bindValue(1, $laptop['name'], SQLITE3_TEXT);
                    $stmt->bindValue(2, $laptop['brand'], SQLITE3_TEXT);
                    $stmt->bindValue(3, $laptop['price_thb'], SQLITE3_INTEGER);
                    $stmt->bindValue(4, $laptop['cpu_model'], SQLITE3_TEXT);
                    $stmt->bindValue(5, $laptop['cpu_cores'], SQLITE3_INTEGER);
                    $stmt->bindValue(6, $laptop['cpu_ghz'], SQLITE3_FLOAT);
                    $stmt->bindValue(7, $laptop['ram_gb'], SQLITE3_INTEGER);
                    $stmt->bindValue(8, $laptop['ram_type'], SQLITE3_TEXT);
                    $stmt->bindValue(9, $laptop['storage_gb'], SQLITE3_INTEGER);
                    $stmt->bindValue(10, $laptop['storage_type'], SQLITE3_TEXT);
                    $stmt->bindValue(11, $laptop['gpu_model'], SQLITE3_TEXT);
                    $stmt->bindValue(12, $laptop['gpu_vram_gb'], SQLITE3_INTEGER);
                    $stmt->bindValue(13, $laptop['llm_performance_score'], SQLITE3_INTEGER);
                    $stmt->bindValue(14, $laptop['shop_url'], SQLITE3_TEXT);
                    $stmt->bindValue(15, $laptop['image_url'], SQLITE3_TEXT);
                    $stmt->bindValue(16, $laptop['last_updated'], SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
        }
        $db->close();
    }
}

// Initialize database
initDatabase();

// Connect to database
$db = new SQLite3(DB_PATH);

// Handle filter parameters
$minPrice = isset($_GET['min_price']) ? intval($_GET['min_price']) : 15000;
$maxPrice = isset($_GET['max_price']) ? intval($_GET['max_price']) : 35000;
$minRam = isset($_GET['min_ram']) ? intval($_GET['min_ram']) : 8;
$minPerformance = isset($_GET['min_performance']) ? intval($_GET['min_performance']) : 40;
$brand = isset($_GET['brand']) ? $_GET['brand'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'performance';

// Build SQL query with filters
$sql = "SELECT * FROM laptops WHERE price_thb BETWEEN :minPrice AND :maxPrice AND ram_gb >= :minRam AND llm_performance_score >= :minPerformance";
$params = [
    ':minPrice' => $minPrice,
    ':maxPrice' => $maxPrice,
    ':minRam' => $minRam,
    ':minPerformance' => $minPerformance
];

if ($brand !== 'all') {
    $sql .= " AND brand = :brand";
    $params[':brand'] = $brand;
}

$orderBy = "llm_performance_score DESC";
if ($sort === 'price_asc') $orderBy = "price_thb ASC";
elseif ($sort === 'price_desc') $orderBy = "price_thb DESC";
elseif ($sort === 'ram') $orderBy = "ram_gb DESC";

$sql .= " ORDER BY $orderBy";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$result = $stmt->execute();
$laptops = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $laptops[] = $row;
}

// Get unique brands for filter
$brandResult = $db->query("SELECT DISTINCT brand FROM laptops ORDER BY brand");
$brands = [];
while ($row = $brandResult->fetchArray(SQLITE3_ASSOC)) {
    $brands[] = $row['brand'];
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Laptop Comparison for Local LLMs in Thailand</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .performance-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.8rem;
        }
        .performance-80 { background-color: #10B981; color: white; }
        .performance-70 { background-color: #3B82F6; color: white; }
        .performance-60 { background-color: #8B5CF6; color: white; }
        .performance-50 { background-color: #F59E0B; color: white; }
        .performance-40 { background-color: #EF4444; color: white; }
        
        .gpu-badge {
            background-color: #1E40AF;
            color: white;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        
        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .filter-active {
            background-color: #3B82F6;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-blue-800">
                        <i class="fas fa-laptop mr-2"></i>Budget Laptop Comparison for Local LLMs
                    </h1>
                    <p class="text-gray-600 mt-2">Find the best budget laptops in Thailand for running local Large Language Models</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <span class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-full">
                        <i class="fas fa-map-marker-alt mr-2"></i> Thailand
                    </span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Filters Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-filter mr-2"></i>Filter Laptops
            </h2>
            
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Price Range -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Price Range (THB)</label>
                    <div class="flex items-center space-x-2">
                        <input type="number" name="min_price" value="<?php echo $minPrice; ?>" min="10000" max="50000" class="w-full p-2 border border-gray-300 rounded">
                        <span class="text-gray-500">to</span>
                        <input type="number" name="max_price" value="<?php echo $maxPrice; ?>" min="10000" max="50000" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
                
                <!-- Minimum RAM -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Minimum RAM (GB)</label>
                    <select name="min_ram" class="w-full p-2 border border-gray-300 rounded">
                        <option value="4" <?php echo $minRam == 4 ? 'selected' : ''; ?>>4 GB</option>
                        <option value="8" <?php echo $minRam == 8 ? 'selected' : ''; ?>>8 GB</option>
                        <option value="12" <?php echo $minRam == 12 ? 'selected' : ''; ?>>12 GB</option>
                        <option value="16" <?php echo $minRam == 16 ? 'selected' : ''; ?>>16 GB</option>
                        <option value="32" <?php echo $minRam == 32 ? 'selected' : ''; ?>>32 GB</option>
                    </select>
                </div>
                
                <!-- Brand Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                    <select name="brand" class="w-full p-2 border border-gray-300 rounded">
                        <option value="all" <?php echo $brand == 'all' ? 'selected' : ''; ?>>All Brands</option>
                        <?php foreach($brands as $brandName): ?>
                            <option value="<?php echo htmlspecialchars($brandName); ?>" <?php echo $brand == $brandName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brandName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Performance Score -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min LLM Performance</label>
                    <select name="min_performance" class="w-full p-2 border border-gray-300 rounded">
                        <option value="30" <?php echo $minPerformance == 30 ? 'selected' : ''; ?>>30+ (Basic)</option>
                        <option value="40" <?php echo $minPerformance == 40 ? 'selected' : ''; ?>>40+ (Fair)</option>
                        <option value="50" <?php echo $minPerformance == 50 ? 'selected' : ''; ?>>50+ (Good)</option>
                        <option value="60" <?php echo $minPerformance == 60 ? 'selected' : ''; ?>>60+ (Very Good)</option>
                        <option value="70" <?php echo $minPerformance == 70 ? 'selected' : ''; ?>>70+ (Excellent)</option>
                    </select>
                </div>
                
                <div class="md:col-span-4 flex justify-end space-x-2 mt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-search mr-2"></i>Apply Filters
                    </button>
                    <a href="?" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">
                        <i class="fas fa-redo mr-2"></i>Reset
                    </a>
                </div>
            </form>
            
            <!-- Quick Filter Buttons -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Quick Filters:</h3>
                <div class="flex flex-wrap gap-2">
                    <a href="?min_price=15000&max_price=20000&min_ram=8&min_performance=50" class="px-3 py-1 text-sm bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200 transition">
                        <i class="fas fa-wallet mr-1"></i> Under 20K THB
                    </a>
                    <a href="?min_price=20000&max_price=25000&min_ram=8&min_performance=60" class="px-3 py-1 text-sm bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200 transition">
                        <i class="fas fa-bolt mr-1"></i> Best Performance
                    </a>
                    <a href="?min_price=15000&max_price=35000&min_ram=16&min_performance=60" class="px-3 py-1 text-sm bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200 transition">
                        <i class="fas fa-memory mr-1"></i> 16GB+ RAM
                    </a>
                    <a href="?min_price=20000&max_price=35000&min_ram=8&min_performance=70&brand=ASUS" class="px-3 py-1 text-sm bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200 transition">
                        <i class="fas fa-desktop mr-1"></i> ASUS Only
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Results Summary -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-list mr-2"></i>Available Laptops
                    <span class="text-lg font-normal text-gray-600 ml-2">(<?php echo count($laptops); ?> found)</span>
                </h2>
                
                <!-- Sort Options -->
                <form method="GET" class="flex items-center">
                    <!-- Preserve existing filters -->
                    <input type="hidden" name="min_price" value="<?php echo $minPrice; ?>">
                    <input type="hidden" name="max_price" value="<?php echo $maxPrice; ?>">
                    <input type="hidden" name="min_ram" value="<?php echo $minRam; ?>">
                    <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand); ?>">
                    <input type="hidden" name="min_performance" value="<?php echo $minPerformance; ?>">
                    
                    <span class="text-gray-700 mr-2">Sort by:</span>
                    <select name="sort" onchange="this.form.submit()" class="bg-white border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="performance" <?php echo $sort == 'performance' ? 'selected' : ''; ?>>LLM Performance</option>
                        <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="ram" <?php echo $sort == 'ram' ? 'selected' : ''; ?>>RAM: High to Low</option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Laptop Grid -->
        <?php if (empty($laptops)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-search fa-3x text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-700">No laptops found</h3>
                <p class="text-gray-500 mt-2">Try adjusting your filters to see more results.</p>
                <a href="?" class="inline-block mt-6 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Reset All Filters
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($laptops as $laptop): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden card-hover">
                        <!-- Laptop Image -->
                        <div class="relative h-48 bg-gray-200 overflow-hidden">
                            <img src="<?php echo htmlspecialchars($laptop['image_url']); ?>" alt="<?php echo htmlspecialchars($laptop['name']); ?>" class="w-full h-full object-cover">
                            <div class="absolute top-2 right-2">
                                <span class="performance-badge performance-<?php echo floor($laptop['llm_performance_score'] / 10) * 10; ?>">
                                    <?php echo $laptop['llm_performance_score']; ?> Score
                                </span>
                            </div>
                        </div>

                        <!-- Laptop Details -->
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider"><?php echo htmlspecialchars($laptop['brand']); ?></span>
                                    <h3 class="text-lg font-bold text-gray-800 leading-tight"><?php echo htmlspecialchars($laptop['name']); ?></h3>
                                </div>
                            </div>

                            <!-- Price -->
                            <div class="mb-4">
                                <span class="text-2xl font-bold text-blue-800">฿<?php echo number_format($laptop['price_thb']); ?></span>
                            </div>

                            <!-- Specs Grid -->
                            <div class="grid grid-cols-2 gap-y-3 gap-x-2 text-sm text-gray-600 mb-5">
                                <div class="flex items-center">
                                    <i class="fas fa-microchip w-5 text-gray-400"></i>
                                    <span class="truncate ml-1" title="<?php echo htmlspecialchars($laptop['cpu_model']); ?>">
                                        <?php echo htmlspecialchars($laptop['cpu_model']); ?>
                                    </span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-memory w-5 text-gray-400"></i>
                                    <span><?php echo $laptop['ram_gb']; ?>GB <?php echo htmlspecialchars($laptop['ram_type']); ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-hdd w-5 text-gray-400"></i>
                                    <span><?php echo $laptop['storage_gb']; ?>GB <?php echo htmlspecialchars($laptop['storage_type']); ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-video w-5 text-gray-400"></i>
                                    <span class="gpu-badge ml-1"><?php echo htmlspecialchars($laptop['gpu_model']); ?></span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div class="pt-4 border-t border-gray-100">
                                <a href="<?php echo htmlspecialchars($laptop['shop_url']); ?>" target="_blank" class="block w-full text-center py-2 bg-blue-50 text-blue-700 font-semibold rounded-lg hover:bg-blue-600 hover:text-white transition">
                                    View on Shop <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- LLM Explanation -->
        <div class="mt-12 bg-blue-900 text-white rounded-2xl p-8 shadow-xl">
            <div class="flex flex-col md:flex-row items-center gap-8">
                <div class="md:w-1/3 text-center">
                    <i class="fas fa-brain fa-5x opacity-20"></i>
                    <h3 class="text-2xl font-bold mt-4">LLM Performance?</h3>
                </div>
                <div class="md:w-2/3">
                    <p class="text-blue-100 leading-relaxed">
                        The performance score indicates how well a laptop can run local Large Language Models (like Llama 3, Mistral, or Phi-3). We consider CPU cores, RAM speed, and GPU capabilities.
                    </p>
                    <ul class="mt-4 space-y-2 text-sm text-blue-200">
                        <li><i class="fas fa-check-circle mr-2 text-green-400"></i> <strong>Score 70+:</strong> Smoothly runs 7B-8B parameter models with decent speed.</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-400"></i> <strong>Score 50-69:</strong> Capable of running 7B models, though response time may vary.</li>
                        <li><i class="fas fa-check-circle mr-2 text-yellow-400"></i> <strong>Score < 50:</strong> Best suited for small models (1B-3B) or basic tasks.</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-8">
        <div class="container mx-auto px-4 text-center text-gray-600">
            <p>&copy; <?php echo date('Y'); ?> Budget Laptop Comparison Thailand. Prices and availability are subject to change.</p>
            <div class="flex justify-center space-x-6 mt-4">
                <a href="#" class="hover:text-blue-600 transition"><i class="fab fa-facebook fa-lg"></i></a>
                <a href="#" class="hover:text-blue-400 transition"><i class="fab fa-twitter fa-lg"></i></a>
                <a href="#" class="hover:text-gray-900 transition"><i class="fab fa-github fa-lg"></i></a>
            </div>
        </div>
    </footer>
</body>
</html>
