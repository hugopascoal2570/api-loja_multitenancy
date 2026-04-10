# Schema Completo do Banco de Dados — api-loja

**Gerado em:** 2026-04-10  
**Projeto:** api-loja (Laravel 13, MySQL 8.4)  
**Total de tabelas:** 35

---

## Índice

1. [Autenticação e Usuários](#1-autenticação-e-usuários)
2. [Controle de Acesso](#2-controle-de-acesso)
3. [Catálogo](#3-catálogo)
4. [Carrinho e Pedidos](#4-carrinho-e-pedidos)
5. [Cupons](#5-cupons)
6. [Estoque](#6-estoque)
7. [Configurações e Marketing](#7-configurações-e-marketing)
8. [Newsletter](#8-newsletter)
9. [Produção Interna](#9-produção-interna)
10. [Integrações Externas](#10-integrações-externas)
11. [Infraestrutura](#11-infraestrutura)
12. [Mapa de Relacionamentos](#12-mapa-de-relacionamentos)
13. [Índices Únicos — Impacto no Multi-Tenancy](#13-índices-únicos--impacto-no-multi-tenancy)

---

## 1. Autenticação e Usuários

### `users`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| name | string | NOT NULL |
| last_name | string | nullable |
| email | string | UNIQUE, NOT NULL |
| email_verified_at | timestamp | nullable |
| password | string | NOT NULL |
| cpf | string(14) | UNIQUE, nullable |
| phone | string | nullable |
| address | string | nullable |
| number | string | nullable |
| neighborhood | string | nullable |
| complement | string | nullable |
| city | string | nullable |
| state | string | nullable |
| zip_code | string | nullable |
| is_admin | boolean | default false |
| remember_token | string | nullable |
| created_at / updated_at | timestamp | — |

### `user_addresses`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| user_id | UUID | FK → users.id CASCADE |
| label | string(50) | nullable |
| recipient_name | string | nullable |
| address | string(255) | NOT NULL |
| number | string(20) | NOT NULL |
| neighborhood | string(100) | NOT NULL |
| complement | string(100) | nullable |
| city | string(100) | NOT NULL |
| state | string(2) | NOT NULL |
| zip_code | string(15) | NOT NULL |
| phone | string(20) | nullable |
| is_default | boolean | default false |
| created_at / updated_at | timestamp | — |

### `password_reset_tokens`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| email | string | PK |
| token | string | NOT NULL |
| created_at | timestamp | nullable |

### `sessions`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | string | PK |
| user_id | bigint | nullable, INDEX |
| ip_address | string(45) | nullable |
| user_agent | text | nullable |
| payload | longtext | NOT NULL |
| last_activity | integer | INDEX |

### `personal_access_tokens`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | bigint | PK AUTO |
| tokenable_type | string | morphs |
| tokenable_id | UUID | morphs |
| name | string | NOT NULL |
| token | string(64) | UNIQUE |
| abilities | text | nullable |
| last_used_at | timestamp | nullable |
| expires_at | timestamp | nullable |
| created_at / updated_at | timestamp | — |

---

## 2. Controle de Acesso

### `permissions`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| name | string | UNIQUE |
| description | string | nullable |
| created_at / updated_at | timestamp | — |

### `permission_user` *(pivot)*

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| permission_id | UUID | FK → permissions.id, PK composta |
| user_id | UUID | FK → users.id CASCADE, PK composta |

---

## 3. Catálogo

### `categories`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| name | string | UNIQUE |
| slug | string | UNIQUE |
| description | text | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `products`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| category_id | UUID | FK → categories.id RESTRICT |
| name | string | NOT NULL |
| slug | string | UNIQUE |
| reference | string | nullable |
| description | longtext | nullable |
| retail_price | decimal(10,2) | nullable |
| wholesale_price | decimal(10,2) | nullable |
| wholesale_min_qty | integer | nullable, default 1 |
| ml_price | decimal(10,2) | nullable |
| is_highlighted | boolean | default false |
| is_promotion | boolean | default false |
| promotion_price | decimal(10,2) | nullable |
| promotion_percent | decimal(5,2) | nullable |
| is_new | boolean | default false |
| is_new_collection | boolean | default false |
| active | boolean | default true |
| weight | decimal(8,3) | nullable (kg) |
| width | decimal(8,1) | nullable (cm) |
| height | decimal(8,1) | nullable (cm) |
| length | decimal(8,1) | nullable (cm) |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `product_variants`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| product_id | UUID | FK → products.id CASCADE |
| size | string | NOT NULL |
| color | string | NOT NULL |
| stock | integer | default 0 |
| sku | string | UNIQUE, nullable |
| barcode | string(50) | UNIQUE, nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `product_images`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| product_id | UUID | FK → products.id CASCADE |
| variant_id | UUID | nullable |
| url | string | NOT NULL |
| color | string | nullable |
| is_main | boolean | default false |
| position | integer | default 0 |
| created_at / updated_at | timestamp | — |

### `product_kits`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| product_id | UUID | FK → products.id CASCADE |
| name | string | nullable |
| description | text | nullable |
| total_quantity | integer | NOT NULL |
| fixed_size | string | nullable |
| fixed_color | string | nullable |
| price | decimal(10,2) | NOT NULL |
| is_featured | boolean | default false |
| is_redistributed | boolean | default false |
| is_active | boolean | default true |
| redistributed_at | timestamp | nullable |
| weight | decimal(8,3) | nullable (kg) |
| width | decimal(8,1) | nullable (cm) |
| height | decimal(8,1) | nullable (cm) |
| length | decimal(8,1) | nullable (cm) |
| created_at / updated_at | timestamp | — |

### `product_kit_items`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| product_kit_id | UUID | FK → product_kits.id CASCADE |
| variant_id | UUID | FK → product_variants.id CASCADE |
| quantity | integer | NOT NULL |
| created_at / updated_at | timestamp | — |

### `product_kit_item_originals` *(snapshot imutável do kit na criação)*

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| product_kit_id | UUID | FK → product_kits.id CASCADE |
| variant_id | UUID | FK → product_variants.id CASCADE |
| quantity | integer | NOT NULL |
| created_at / updated_at | timestamp | — |

### `product_measurements`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| product_id | UUID | FK → products.id CASCADE |
| size | string(10) | NOT NULL |
| bust | decimal(6,1) | nullable (cm) |
| waist | decimal(6,1) | nullable (cm) |
| hip | decimal(6,1) | nullable (cm) |
| waistband | decimal(6,1) | nullable (cm) |
| rise | decimal(6,1) | nullable (cm) |
| inseam | decimal(6,1) | nullable (cm) |
| thigh | decimal(6,1) | nullable (cm) |
| length | decimal(6,1) | nullable (cm) |
| shoulder | decimal(6,1) | nullable (cm) |
| sleeve | decimal(6,1) | nullable (cm) |
| measurement_image | string | nullable |
| sort_order | smallint | default 0 |
| created_at / updated_at | timestamp | — |
| — | UNIQUE | (product_id, size) |

---

## 4. Carrinho e Pedidos

### `carts`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| user_id | UUID | FK → users.id SET NULL, nullable |
| token | string | UNIQUE |
| status | enum(pending, completed) | default 'pending' |
| created_at / updated_at | timestamp | — |

### `cart_items`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | bigint | PK AUTO |
| cart_id | UUID | FK → carts.id CASCADE |
| product_id | UUID | NOT NULL |
| type | string | nullable |
| variant_id | UUID | nullable |
| kit_id | UUID | nullable |
| quantity | integer | NOT NULL |
| unit_price | decimal(10,2) | NOT NULL |
| total_price | decimal(10,2) | NOT NULL |
| created_at / updated_at | timestamp | — |

### `orders`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | bigint | PK AUTO |
| order_number | string | UNIQUE |
| user_id | UUID | FK → users.id SET NULL, nullable |
| cart_id | UUID | nullable |
| coupon_id | UUID | FK → coupons.id SET NULL, nullable |
| coupon_code | string | nullable |
| discount_amount | decimal(10,2) | default 0 |
| total_amount | decimal(10,2) | NOT NULL |
| payment_method | string | NOT NULL |
| payment_id | string | nullable |
| refund_id | string | nullable |
| refund_amount | decimal(12,2) | nullable |
| canceled_at | timestamp | nullable |
| cancel_reason | text | nullable |
| status | enum(pending, approved, rejected, cancelled, refunded, shipped, delivered, cancellation_requested) | NOT NULL |
| source | string | default 'online' |
| customer_name | string | nullable (venda de balcão) |
| customer_phone | string | nullable (venda de balcão) |
| ml_order_id | string | UNIQUE, nullable |
| ml_shipment_id | string | nullable |
| ml_content_declared_at | timestamp | nullable |
| excursion_info | text | nullable |
| delivery_fee | decimal(10,2) | default 0 |
| delivery_method | string | default 'delivery' |
| shipping_service_id | unsignedInteger | nullable |
| shipping_service_name | string | nullable |
| shipping_estimated_days | unsignedInteger | nullable |
| tracking_code | string | nullable |
| shipping_status | string | nullable, default 'pending' |
| melhor_envio_order_id | string | nullable |
| melhor_envio_protocol | string | nullable |
| melhor_envio_label_url | string | nullable |
| melhor_envio_paid_at | timestamp | nullable |
| melhor_envio_generated_at | timestamp | nullable |
| melhor_envio_posted_at | timestamp | nullable |
| melhor_envio_delivered_at | timestamp | nullable |
| shipping_address | string | nullable |
| shipping_number | string(20) | nullable |
| shipping_neighborhood | string(100) | nullable |
| shipping_complement | string(100) | nullable |
| shipping_city | string(100) | nullable |
| shipping_state | string(2) | nullable |
| shipping_zip_code | string(15) | nullable |
| shipping_recipient_name | string | nullable |
| shipping_phone | string(20) | nullable |
| created_at / updated_at | timestamp | — |

### `order_items`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | bigint | PK AUTO |
| order_id | bigint | FK → orders.id CASCADE |
| product_id | UUID | nullable |
| variant_id | UUID | nullable |
| kit_id | UUID | nullable |
| quantity | integer | NOT NULL |
| unit_price | decimal(10,2) | NOT NULL |
| total_price | decimal(10,2) | NOT NULL |
| color | string | nullable |
| size | string | nullable |
| created_at / updated_at | timestamp | — |

---

## 5. Cupons

### `coupons`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| code | string | UNIQUE |
| type | enum(fixed, percentage) | NOT NULL |
| value | decimal(10,2) | NOT NULL |
| max_uses | integer | nullable (null = ilimitado) |
| max_uses_per_user | integer | default 1 |
| current_uses | integer | default 0 |
| valid_from | date | nullable |
| valid_until | date | nullable |
| is_active | boolean | default true |
| created_at / updated_at | timestamp | — |

### `coupon_usages`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| coupon_id | UUID | FK → coupons.id CASCADE |
| user_id | UUID | FK → users.id CASCADE |
| order_id | UUID | nullable |
| used_at | timestamp | NOT NULL |
| created_at / updated_at | timestamp | — |

---

## 6. Estoque

### `inventory_movements`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| product_variant_id | UUID | FK → product_variants.id CASCADE |
| type | enum(in, out, adjustment) | NOT NULL |
| quantity | integer | NOT NULL |
| stock_before | integer | NOT NULL |
| stock_after | integer | NOT NULL |
| reason | enum(sale, cancellation, refund, manual_add, manual_remove, manual_set) | nullable |
| related_order_id | bigint | FK → orders.id SET NULL, nullable |
| notes | text | nullable |
| user_id | string | nullable |
| reversal_of_id | UUID | FK → inventory_movements.id, nullable |
| reversed_by | string | nullable |
| reversed_at | timestamp | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

---

## 7. Configurações e Marketing

### `settings`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| key | string | UNIQUE |
| value | text | nullable |
| default_value | text | nullable |
| type | string | default 'string' |
| group | string | nullable |
| description | string | nullable |
| label | string | nullable |
| options | json | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `banners`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| name | string | NOT NULL |
| description | text | nullable |
| link | string | NOT NULL |
| is_featured | boolean | default false |
| position | unsignedInteger | default 1 |
| image_url | string | NOT NULL |
| start_date | datetime | nullable |
| end_date | datetime | nullable |
| active | boolean | default true |
| device_type | enum(all, desktop, mobile) | default 'all' |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `admin_notifications`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| type | string | NOT NULL |
| title | string | NOT NULL |
| body | text | NOT NULL |
| data | json | nullable |
| is_read | boolean | default false |
| read_at | timestamp | nullable |
| created_at / updated_at | timestamp | — |

### `delivery_settings` *(row única — configuração global de entrega)*

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | bigint | PK AUTO |
| is_delivery_enabled | boolean | default true |
| delivery_fee | decimal(10,2) | default 0 |
| minimum_order_value | decimal(10,2) | default 0 |
| minimum_order_message | text | nullable |
| description | text | nullable |
| is_pickup_enabled | boolean | default false |
| pickup_address | text | nullable |
| pickup_instructions | text | nullable |
| is_dynamic_shipping_enabled | boolean | default false |
| origin_zip_code | string | nullable |
| default_weight | decimal(8,3) | default 0.300 (kg) |
| default_width | decimal(8,1) | default 20.0 (cm) |
| default_height | decimal(8,1) | default 10.0 (cm) |
| default_length | decimal(8,1) | default 30.0 (cm) |
| store_notice | text | nullable |
| is_store_open | boolean | default true |
| cutoff_day | string(20) | default 'friday' |
| cutoff_time | time | default '11:00:00' |
| start_day | string | default 'monday' |
| next_delivery_message | text | nullable |
| created_at / updated_at | timestamp | — |

---

## 8. Newsletter

### `newsletters`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| title | string | NOT NULL |
| content | text | NOT NULL |
| image_path | string | nullable |
| status | enum(draft, scheduled, sending, sent, failed) | default 'draft' |
| scheduled_at | timestamp | nullable |
| sent_at | timestamp | nullable |
| total_recipients | integer | default 0 |
| created_by | UUID | FK → users.id CASCADE |
| created_at / updated_at | timestamp | — |

### `newsletter_subscribers`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| email | string | UNIQUE |
| name | string | nullable |
| status | enum(active, unsubscribed) | default 'active' |
| unsubscribe_token | string | UNIQUE |
| subscribed_at | timestamp | NOT NULL |
| unsubscribed_at | timestamp | nullable |
| created_at / updated_at | timestamp | — |

---

## 9. Produção Interna

### `cuts`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| cut_number | unsignedInteger | UNIQUE |
| cutting_labor_cost | decimal(10,2) | default 0 |
| status | enum(pending, in_progress, completed) | default 'pending' |
| notes | text | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `fabric_rolls`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| cut_id | UUID | FK → cuts.id CASCADE |
| color | string(100) | NOT NULL |
| quantity_rolls | integer | default 1 |
| meters | decimal(8,2) | NOT NULL |
| price_per_roll | decimal(10,2) | nullable |
| price_per_meter | decimal(10,2) | nullable |
| notes | text | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `cut_productions`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| cut_id | UUID | FK → cuts.id CASCADE |
| fabric_roll_id | UUID | FK → fabric_rolls.id CASCADE |
| product_id | UUID | FK → products.id SET NULL, nullable |
| product_variant_id | UUID | FK → product_variants.id SET NULL, nullable |
| product_description | string | nullable |
| quantity_produced | integer | NOT NULL |
| fabric_meters_used | decimal(8,2) | nullable |
| notes | text | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `seamstresses`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| name | string | NOT NULL |
| phone | string(20) | nullable |
| address | text | nullable |
| price_per_piece | decimal(10,2) | default 0 |
| is_active | boolean | default true |
| notes | text | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `seamstress_costs`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| seamstress_id | UUID | FK → seamstresses.id CASCADE |
| name | string | NOT NULL |
| price | decimal(10,2) | NOT NULL |
| cost_type | enum(per_piece, fixed) | default 'per_piece' |
| is_active | boolean | default true |
| notes | text | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `seamstress_distributions`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| cut_id | UUID | FK → cuts.id CASCADE |
| created_by | UUID | FK → users.id SET NULL, nullable |
| notes | string | nullable |
| assigned_at | timestamp | NOT NULL |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

### `seamstress_assignments`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | UUID | PK |
| seamstress_id | UUID | FK → seamstresses.id CASCADE |
| cut_production_id | UUID | FK → cut_productions.id CASCADE |
| distribution_id | UUID | FK → seamstress_distributions.id CASCADE |
| quantity_assigned | integer | NOT NULL |
| quantity_returned | integer | default 0 |
| quantity_defective | integer | default 0 |
| price_per_piece | decimal(10,2) | NOT NULL |
| status | enum(assigned, in_progress, returned) | default 'assigned' |
| assigned_at | timestamp | NOT NULL |
| returned_at | timestamp | nullable |
| notes | text | nullable |
| created_at / updated_at | timestamp | — |
| deleted_at | timestamp | soft delete |

---

## 10. Integrações Externas

### `mercado_livre_tokens`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | bigint | PK AUTO |
| seller_id | string | UNIQUE |
| access_token | text | NOT NULL |
| refresh_token | text | NOT NULL |
| expires_at | timestamp | NOT NULL |
| created_at / updated_at | timestamp | — |

### `mercado_livre_listings`

| Coluna | Tipo | Restrições |
|--------|------|-----------|
| id | bigint | PK AUTO |
| product_id | UUID | FK → products.id CASCADE |
| ml_item_id | string | UNIQUE, INDEX |
| status | enum(active, paused, closed, pending, error) | default 'pending' |
| ml_category_id | string | nullable |
| ml_piece_chart_id | string | nullable |
| synced_at | timestamp | nullable |
| last_error | text | nullable |
| created_at / updated_at | timestamp | — |

### `store_configurations` *(row única — credenciais e configurações da loja)*

| Coluna | Tipo | Notas |
|--------|------|-------|
| id | bigint | PK AUTO |
| **Melhor Envio** | | |
| melhor_envio_token | text | nullable, encrypted |
| melhor_envio_app_secret | text | nullable, encrypted |
| melhor_envio_sandbox | boolean | default false |
| melhor_envio_phone | string | nullable |
| melhor_envio_email | string | nullable |
| melhor_envio_document | text | nullable, encrypted (CPF/CNPJ) |
| melhor_envio_company_document | text | nullable, encrypted |
| melhor_envio_state_register | string | nullable |
| melhor_envio_address | string | nullable |
| melhor_envio_complement | string | nullable |
| melhor_envio_number | string | nullable |
| melhor_envio_district | string | nullable |
| melhor_envio_city | string | nullable |
| melhor_envio_state_abbr | string(2) | nullable |
| **Mercado Livre** | | |
| ml_client_id | text | nullable, encrypted |
| ml_client_secret | text | nullable, encrypted |
| ml_redirect_uri | string | nullable |
| **Mercado Pago** | | |
| mp_access_token | text | nullable, encrypted |
| mp_public_key | text | nullable, encrypted |
| mp_webhook_secret | text | nullable, encrypted |
| mp_enforce_signature | boolean | default true |
| **Telegram** | | |
| telegram_bot_token | text | nullable, encrypted |
| telegram_chat_id | string | nullable |
| admin_notification_email | string | nullable |
| **Super Admins** | | |
| super_admin_emails | text | nullable, encrypted JSON |
| **SMTP** | | |
| mail_mailer | string | nullable |
| mail_host | string | nullable |
| mail_port | unsignedSmallInteger | nullable |
| mail_username | text | nullable, encrypted |
| mail_password | text | nullable, encrypted |
| mail_encryption | string | nullable |
| mail_from_address | string | nullable |
| mail_from_name | string | nullable |
| created_at / updated_at | timestamp | — |

---

## 11. Infraestrutura

### `cache`

| Coluna | Tipo |
|--------|------|
| key | string (PK) |
| value | mediumtext |
| expiration | integer |

### `jobs`

| Coluna | Tipo |
|--------|------|
| id | bigint (PK AUTO) |
| queue | string, INDEX |
| payload | longtext |
| attempts | tinyint |
| reserved_at | unsignedInteger, nullable |
| available_at | unsignedInteger |
| created_at | unsignedInteger |

### `failed_jobs`

| Coluna | Tipo |
|--------|------|
| id | bigint (PK AUTO) |
| uuid | string, UNIQUE |
| connection | text |
| queue | text |
| payload | longtext |
| exception | longtext |
| failed_at | timestamp |

---

## 12. Mapa de Relacionamentos

```
┌─────────────────────────────────────────────────────────────────────┐
│  CATÁLOGO                                                           │
│                                                                     │
│  categories ──< products ──< product_variants                       │
│                    │               │                                │
│                    │               ├──< product_images              │
│                    │               ├──< product_kit_items           │
│                    │               ├──< product_kit_item_originals  │
│                    │               ├──< cart_items                  │
│                    │               ├──< order_items                 │
│                    │               ├──< inventory_movements         │
│                    │               └──< cut_productions             │
│                    │                                                │
│                    ├──< product_kits (via product_id)               │
│                    ├──< product_measurements                        │
│                    └──< mercado_livre_listings                      │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  VENDAS                                                             │
│                                                                     │
│  users ──< orders ──< order_items                                   │
│    │          │                                                     │
│    │          └── coupon_id ──> coupons ──< coupon_usages ── users  │
│    │                                                                │
│    ├──< carts ──< cart_items                                        │
│    ├──< user_addresses                                              │
│    └──< coupon_usages                                               │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  CONTROLE DE ACESSO                                                 │
│                                                                     │
│  users >──< permission_user >──< permissions                        │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  ESTOQUE                                                            │
│                                                                     │
│  product_variants ──< inventory_movements                           │
│                             │                                       │
│                             └── related_order_id ──> orders        │
│                             └── reversal_of_id ──> inventory_movements (self) │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  PRODUÇÃO INTERNA                                                   │
│                                                                     │
│  cuts ──< fabric_rolls ──< cut_productions                          │
│   │                             │                                   │
│   │                             ├── product_id ──> products         │
│   │                             └── product_variant_id ──> product_variants │
│   │                                                                 │
│   └──< seamstress_distributions ──< seamstress_assignments          │
│                                          │                          │
│                                          └── seamstress_id ──> seamstresses ──< seamstress_costs │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  NEWSLETTER                                                         │
│                                                                     │
│  users ──< newsletters (created_by)                                 │
│  newsletter_subscribers  (independente)                             │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  MERCADO LIVRE                                                      │
│                                                                     │
│  mercado_livre_tokens  (row única por conta OAuth)                  │
│  mercado_livre_listings ── product_id ──> products                  │
│  orders.ml_order_id  (referência externa)                           │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 13. Índices Únicos — Impacto no Multi-Tenancy

Todos os índices abaixo precisarão se tornar **compostos com `tenant_id`** na migração para multi-tenancy.

| Tabela | Índice único atual | Índice futuro |
|--------|-------------------|--------------|
| `products` | `slug` | `(tenant_id, slug)` |
| `product_variants` | `sku` | `(tenant_id, sku)` |
| `product_variants` | `barcode` | `(tenant_id, barcode)` |
| `categories` | `name` | `(tenant_id, name)` |
| `categories` | `slug` | `(tenant_id, slug)` |
| `users` | `email` | `(tenant_id, email)` |
| `users` | `cpf` | `(tenant_id, cpf)` |
| `coupons` | `code` | `(tenant_id, code)` |
| `cuts` | `cut_number` | `(tenant_id, cut_number)` |
| `newsletter_subscribers` | `email` | `(tenant_id, email)` |
| `newsletter_subscribers` | `unsubscribe_token` | `(tenant_id, unsubscribe_token)` |
| `settings` | `key` | `(tenant_id, key)` |
| `mercado_livre_tokens` | `seller_id` | `(tenant_id, seller_id)` |
| `mercado_livre_listings` | `ml_item_id` | `(tenant_id, ml_item_id)` |
| `permissions` | `name` | Global — permissões são do sistema, não por tenant |

> **Nota:** `delivery_settings` e `store_configurations` são tabelas de row única (assumem empresa única).
> Na migração para multi-tenancy, ambas precisarão de `tenant_id` tornando-se uma row por tenant.
