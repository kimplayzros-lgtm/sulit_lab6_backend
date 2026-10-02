<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Products extends Controller
{
    private function api()
    {
        $api = $this->call->library('api');
        $api->require_jwt();
        return $api;
    }

    private function validated_product($api, $input)
    {
        $name = trim(htmlspecialchars_decode((string) ($input['product_name'] ?? ''), ENT_QUOTES));
        $description = htmlspecialchars_decode((string) ($input['description'] ?? ''), ENT_QUOTES);
        $price = $input['price'] ?? null;
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);

        if ($name === '' || strlen($name) > 100 || !is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99 || $quantity === false || $quantity < 0) {
            $api->respond_error('Product name, valid non-negative price, and whole-number quantity are required.', 422);
        }

        return [$name, $description, number_format((float) $price, 2, '.', ''), $quantity];
    }

    public function preflight()
    {
        $this->call->library('api')->respond(['status' => 'ok']);
    }

    public function index()
    {
        $api = $this->api();
        $api->require_method('GET');
        $products = $this->call->database()->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products ORDER BY created_at DESC, id DESC'
        )->fetchAll();
        $api->respond(['products' => $products]);
    }

    public function show($id)
    {
        $api = $this->api();
        $api->require_method('GET');
        $product = $this->call->database()->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [(int) $id]
        )->fetch();
        if (!$product) {
            $api->respond_error('Product not found.', 404);
        }
        $api->respond(['product' => $product]);
    }

    public function store()
    {
        $api = $this->api();
        $api->require_method('POST');
        $input = $api->body();
        [$name, $description, $price, $quantity] = $this->validated_product($api, $input);
        $db = $this->call->database();
        $db->raw('INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)', [$name, $description, $price, $quantity]);
        $new_id = (int) $db->raw('SELECT LAST_INSERT_ID()')->fetchColumn();
        $product = $db->raw('SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ?', [$new_id])->fetch();
        $api->respond(['message' => 'Product created.', 'product' => $product], 201);
    }

    public function update($id)
    {
        $api = $this->api();
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
            $api->respond_error('Method Not Allowed.', 405);
        }

        $db = $this->call->database();
        $current = $db->raw('SELECT id, product_name, description, price, quantity FROM products WHERE id = ? LIMIT 1', [(int) $id])->fetch();
        if (!$current) {
            $api->respond_error('Product not found.', 404);
        }

        $input = $api->body();
        if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
            $input = array_merge($current, $input);
        }
        [$name, $description, $price, $quantity] = $this->validated_product($api, $input);
        $db->raw('UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?', [$name, $description, $price, $quantity, (int) $id]);
        $product = $db->raw('SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1', [(int) $id])->fetch();
        $api->respond(['message' => 'Product updated.', 'product' => $product]);
    }

    public function destroy($id)
    {
        $api = $this->api();
        $api->require_method('DELETE');
        $statement = $this->call->database()->raw('DELETE FROM products WHERE id = ?', [(int) $id]);
        if ($statement->rowCount() === 0) {
            $api->respond_error('Product not found.', 404);
        }
        $api->respond(['message' => 'Product deleted.']);
    }
}