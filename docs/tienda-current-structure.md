# Current store structure

This repository has a catalog-style store, not a full order-management system.

## Data model

- `products` table: title, slug, image, price, regular_price, category, description, is_sale, is_published, sort_order.
- No `orders`, `order_items`, `cart`, or `categories` tables were found in the application code or migrations.

## Model

- `app/Models/Product.php`
- Supports published/ordered scopes and catalog serialization via `toCatalogArray()`.

## Controllers

- `app/Http/Controllers/Admin/ProductController.php`
  - Creates, updates and deletes catalog products from the admin panel.
  - Stores images either as a local public path or as a string URL.

- `app/Http/Controllers/SiteController.php`
  - `shop()` renders the catalog page.
  - `productSingle()` renders the product detail page.

## Views

- `resources/views/pages/shop.blade.php`
- `resources/views/pages/product-single.blade.php`
- Admin product CRUD views under `resources/views/admin/products/`

## Current behavior

- Products are published as catalog items.
- Product detail pages still show a classic add-to-cart UI placeholder.
- There is no checkout or order persistence implemented in this repo for the store.

## Talents adaptation

The Talents storefront uses the same `products` table, but adds:

- `talent_id`
- `is_talent_product`
- `external_payment_url`
- `external_payment_label`

Those fields let a talent expose products in their public profile and redirect buyers to an external payment page without handling money inside Seven Rock Radio.
