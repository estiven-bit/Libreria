<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../utils/Sanitizer.php';

class ProductController
{
    private PDO $db;
    private array $app;

    public function __construct(PDO $db, array $app = [])
    {
        $this->db = $db;
        $this->app = $app;
    }

    public function list(array $filters): void
    {
        $model = new Product($this->db);
        $products = $model->list($filters);
        foreach ($products as &$product) {
            $product = $this->withPublicImageUrls($product);
        }
        unset($product);

        $total = $model->count($filters);
        Response::json([
            'data' => $products,
            'total' => $total,
            'page' => isset($filters['page']) ? (int)$filters['page'] : 1,
            'limit' => isset($filters['limit']) ? (int)$filters['limit'] : 0,
        ]);
    }

    public function show(int $id): void
    {
        $model = new Product($this->db);
        $product = $model->findWithDetails($id);
        if (!$product) {
            Response::json(['error' => 'Product not found'], 404);
        }

        $images = $product['images'] ?? [];
        $product['images'] = array_map(
            fn(array $img) => [
                'id' => (int)$img['id'],
                'image_url' => $this->imagePublicPath($id, (int)$img['id']),
            ],
            $images
        );
        $product['image_url'] = $product['images'][0]['image_url'] ?? null;
        unset($product['category_name'], $product['category_parent_id']);

        Response::json(['data' => $product]);
    }

    public function serveImage(int $productId, int $imageId): void
    {
        $stmt = $this->db->prepare(
            'SELECT image_url FROM product_images WHERE id = :image_id AND product_id = :product_id LIMIT 1'
        );
        $stmt->execute(['image_id' => $imageId, 'product_id' => $productId]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::json(['error' => 'Image not found'], 404);
        }

        $stored = (string)$row['image_url'];
        if (str_starts_with($stored, 'data:') && preg_match('#^data:([^;]+);base64,(.+)$#s', $stored, $m)) {
            $binary = base64_decode($m[2], true);
            if ($binary === false) {
                Response::json(['error' => 'Invalid image data'], 500);
            }
            http_response_code(200);
            header('Content-Type: ' . $m[1]);
            header('Cache-Control: public, max-age=86400');
            echo $binary;
            exit;
        }

        if (str_starts_with($stored, '/uploads/')) {
            $path = rtrim($this->app['uploads_path'] ?? '', '/') . '/' . basename($stored);
            if (is_file($path)) {
                $mime = mime_content_type($path) ?: 'application/octet-stream';
                http_response_code(200);
                header('Content-Type: ' . $mime);
                header('Cache-Control: public, max-age=86400');
                readfile($path);
                exit;
            }
        }

        Response::json(['error' => 'Image not found'], 404);
    }

    private function imagePublicPath(int $productId, int $imageId): string
    {
        return '/api/products/' . $productId . '/images/' . $imageId;
    }

    private function withPublicImageUrls(array $product): array
    {
        $imageId = isset($product['primary_image_id']) ? (int)$product['primary_image_id'] : 0;
        if ($imageId > 0) {
            $product['image_url'] = $this->imagePublicPath((int)$product['id'], $imageId);
        }
        unset($product['primary_image_id']);
        return $product;
    }

    public function create(array $data): void
    {
        $model = new Product($this->db);
        $id = $model->create([
            'name' => Sanitizer::string($data['name'] ?? ''),
            'description' => Sanitizer::string($data['description'] ?? ''),
            'price' => (float)($data['price'] ?? 0),
            'stock' => (int)($data['stock'] ?? 0),
            'category_id' => (int)($data['category_id'] ?? 0),
        ]);
        Response::json(['message' => 'Product created', 'id' => $id], 201);
    }

    public function update(int $id, array $data): void
    {
        $model = new Product($this->db);
        $model->update($id, [
            'name' => Sanitizer::string($data['name'] ?? ''),
            'description' => Sanitizer::string($data['description'] ?? ''),
            'price' => (float)($data['price'] ?? 0),
            'stock' => (int)($data['stock'] ?? 0),
            'category_id' => (int)($data['category_id'] ?? 0),
        ]);
        Response::json(['message' => 'Product updated']);
    }

    public function delete(int $id): void
    {
        $model = new Product($this->db);
        $model->delete($id);
        Response::json(['message' => 'Product deleted']);
    }

    public function uploadProductImage(int $productId): void
    {
        $model = new Product($this->db);
        if (!$model->find($productId)) {
            Response::json(['error' => 'Product not found'], 404);
        }

        if (!isset($_FILES['image']) || (int)($_FILES['image']['error'] ?? 0) !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'No image provided'], 422);
        }

        $file = $_FILES['image'];
        $tmp = $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            Response::json(['error' => 'Invalid upload'], 422);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            Response::json(['error' => 'Solo se permiten JPG o PNG'], 422);
        }

        // Comprimir y redimensionar la imagen si es demasiado grande antes de guardarla
        $imgData = $this->compressAndResizeImage($tmp, $mime);
        if (strlen($imgData) > 900000) {
            Response::json(['error' => 'La imagen sigue siendo demasiado grande tras comprimirla. Prueba con otra más pequeña.'], 422);
        }

        $base64 = base64_encode($imgData);
        $url = 'data:' . $mime . ';base64,' . $base64;

        try {
            $stmt = $this->db->prepare('INSERT INTO product_images (product_id, image_url) VALUES (:pid, :url)');
            $stmt->execute(['pid' => $productId, 'url' => $url]);
        } catch (\PDOException $e) {
            Response::json(['error' => 'No se pudo guardar la imagen en la base de datos'], 500);
        }

        $imageId = (int)$this->db->lastInsertId();
        Response::json([
            'message' => 'Image uploaded',
            'image_url' => $this->imagePublicPath($productId, $imageId),
            'id' => $imageId,
        ], 201);
    }

    /**
     * Redimensiona la imagen a max 1000px y la comprime para evitar superar
     * el límite de max_allowed_packet de MySQL al guardarla como base64.
     */
    private function compressAndResizeImage(string $filePath, string $mime): string
    {
        if (!extension_loaded('gd') || !in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return file_get_contents($filePath);
        }

        $srcImage = $mime === 'image/jpeg' 
            ? @imagecreatefromjpeg($filePath) 
            : @imagecreatefrompng($filePath);

        if (!$srcImage) {
            return file_get_contents($filePath);
        }

        $width = imagesx($srcImage);
        $height = imagesy($srcImage);
        $maxSize = 1000;

        if ($width > $maxSize || $height > $maxSize) {
            if ($width > $height) {
                $newWidth = $maxSize;
                $newHeight = (int)($height * ($maxSize / $width));
            } else {
                $newHeight = $maxSize;
                $newWidth = (int)($width * ($maxSize / $height));
            }

            $dstImage = imagecreatetruecolor($newWidth, $newHeight);
            
            if ($mime === 'image/png') {
                imagealphablending($dstImage, false);
                imagesavealpha($dstImage, true);
            }

            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($srcImage);
            $srcImage = $dstImage;
        }

        ob_start();
        if ($mime === 'image/jpeg') {
            imagejpeg($srcImage, null, 75); // 75% calidad para JPG
        } else {
            imagepng($srcImage, null, 6); // 6/9 compresión para PNG
        }
        $data = ob_get_clean();
        imagedestroy($srcImage);

        return $data;
    }

    public function listReviews(int $productId): void
    {
        $stmt = $this->db->prepare('
            SELECT r.id, r.rating, r.comment, r.created_at, r.user_id, u.name AS user_name 
            FROM product_reviews r 
            INNER JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = :pid 
            ORDER BY r.created_at DESC
        ');
        $stmt->execute(['pid' => $productId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $purchaseStmt = $this->db->prepare('
            SELECT COUNT(*) 
            FROM orders o 
            INNER JOIN order_items oi ON o.id = oi.order_id 
            WHERE o.user_id = :user_id 
              AND oi.product_id = :product_id 
              AND o.status = \'delivered\'
        ');

        foreach ($reviews as &$r) {
            $purchaseStmt->execute([
                'user_id' => (int)$r['user_id'],
                'product_id' => $productId,
            ]);
            $count = (int)$purchaseStmt->fetchColumn();
            $r['verified_purchase'] = ($count > 0);
            unset($r['user_id']);
        }
        unset($r);

        Response::json(['data' => $reviews]);
    }

    public function createReview(int $productId, int $userId, array $body): void
    {
        $rating = isset($body['rating']) ? (int)$body['rating'] : 0;
        $comment = isset($body['comment']) ? trim((string)$body['comment']) : '';

        if ($rating < 1 || $rating > 5 || $comment === '') {
            Response::json(['error' => 'La valoración (1-5) y el comentario son obligatorios.'], 422);
            return;
        }

        $stmt = $this->db->prepare('
            INSERT INTO product_reviews (product_id, user_id, rating, comment, created_at) 
            VALUES (:pid, :uid, :rating, :comment, NOW())
        ');
        $stmt->execute([
            'pid' => $productId,
            'uid' => $userId,
            'rating' => $rating,
            'comment' => $comment,
        ]);

        Response::json(['message' => 'Comentario añadido con éxito.'], 201);
    }
}
