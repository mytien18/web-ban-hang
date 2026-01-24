// Nếu bạn vẫn gặp lỗi ảnh, hãy chạy lệnh SQL này để sửa dữ liệu DB:
// UPDATE product SET thumbnail = CONCAT('/', thumbnail) WHERE thumbnail NOT LIKE '/%';
// UPDATE product SET image = CONCAT('/', image) WHERE image NOT LIKE '/%';

import Image from "next/image";
import Link from "next/link";
import Script from "next/script";
import { notFound } from "next/navigation";
import Gallery from "./Gallery";
import RecentlyViewed from "./RecentlyViewed";
import { ProductFavoriteButton } from "./ProductClientWrapper";
import ProductCoupons from "@/components/ProductCoupons";
import ProductReviewsSection from "./ProductReviewsSection";
import VariantPricingBox from "./VariantPricingBox";
import RelatedProductCard from "./RelatedProductCard";

const API_BASE = (process.env.NEXT_PUBLIC_API_BASE || "http://127.0.0.1:8000").replace(/\/+$/, "");

/* --------- Helpers --------- */
async function fetchJSON(url) {
  try {
    const res = await fetch(url, { cache: "no-store" });
    if (!res.ok) return null;
    return res.json();
  } catch {
    return null;
  }
}

function normImg(src) {
  if (!src) return "/slide1.jpg";
  const s = typeof src === "string" ? src : src.image || src.url || src.src || "";
  if (!s) return "/slide1.jpg";

  // Debug log để xem src gốc
  console.log("normImg input:", s);

  // Nếu đã là URL tuyệt đối thì giữ nguyên
  if (s.startsWith("http")) return s;

  // Nếu đã bắt đầu bằng "storage/" thì thêm prefix /api/v1
  if (s.startsWith("storage/")) {
    const result = `${API_BASE}/api/v1/${s}`;
    console.log("normImg storage result:", result);
    return result;
  }

  // Nếu đã bắt đầu bằng "/" thì thêm API_BASE
  if (s.startsWith("/")) {
    const result = `${API_BASE}/api/v1${s}`;
    console.log("normImg slash result:", result);
    return result;
  }

  // Trường hợp fallback - nếu không phải URL hợp lệ thì dùng ảnh mặc định
  console.log("normImg fallback to default image");
  return "/slide1.jpg";
}

// Kiểm tra sale có đang active không (dựa trên date_begin và date_end)
function isSaleActive(sale) {
  if (!sale) return false;
  if (String(sale.status ?? 1) !== "1") return false;
  const now = new Date();
  const dateBegin = sale.date_begin ? new Date(sale.date_begin) : null;
  const dateEnd = sale.date_end ? new Date(sale.date_end) : null;
  if (dateBegin && now < dateBegin) return false;
  if (dateEnd && now > dateEnd) return false;
  return true;
}

// Hàm normalize cho SSR
function normalizeProductForSSR(product) {
  if (!product) return product;
  
  const normalized = { ...product };
  
  // Normalize thumbnail/image
  if (normalized.thumbnail) {
    normalized.thumbnail = normImg(normalized.thumbnail);
  }
  if (normalized.image) {
    normalized.image = normImg(normalized.image);
  }
  
  // Normalize images array
  if (Array.isArray(normalized.images)) {
    normalized.images = normalized.images.map(img => normImg(typeof img === "string" ? img : img.image || img.url || img.src || ""));
  }

  // ✅ Xử lý product_sale: kiểm tra sale active từ bảng product_sale
  let activeSale = null;
  if (normalized.product_sale && isSaleActive(normalized.product_sale)) {
    activeSale = normalized.product_sale;
  } else if (Array.isArray(normalized.sales) && normalized.sales.length > 0) {
    // Nếu có mảng sales, tìm sale active đầu tiên
    activeSale = normalized.sales.find(s => isSaleActive(s)) || null;
  }
  
  // ✅ Cập nhật price_sale nếu có sale active từ bảng product_sale
  if (activeSale && activeSale.price_sale) {
    const salePrice = Number(activeSale.price_sale);
    const basePrice = Number(normalized.price_buy ?? 0);
    if (salePrice > 0 && salePrice < basePrice) {
      normalized.price_sale = salePrice;
      normalized.product_sale = activeSale; // Giữ lại thông tin sale
    }
  }

  // ✅ Xử lý attributes nếu có
  if (Array.isArray(normalized.attributes)) {
    normalized.attributes = normalized.attributes.map(attr => ({
      id: attr.id,
      attribute_id: attr.attribute_id,
      value: attr.value,
      attribute_name: attr.attribute?.name || null,
    }));
  }

  // ✅ Đảm bảo available_quantity từ product_store (backend đã tính sẵn)
  if (normalized.available_quantity === undefined || normalized.available_quantity === null) {
    normalized.available_quantity = 0;
  }
  if (normalized.is_in_stock === undefined || normalized.is_in_stock === null) {
    normalized.is_in_stock = normalized.available_quantity > 0;
  }

  if (Array.isArray(normalized.variants)) {
    normalized.variants = normalized.variants.map((variant) => {
      const copy = { ...variant };
      copy.price = Number(variant.price ?? 0);
      copy.price_sale = variant.price_sale === null || variant.price_sale === undefined ? 0 : Number(variant.price_sale);
      copy.effective_price = Number(variant.effective_price ?? ((copy.price_sale > 0 && copy.price_sale < copy.price) ? copy.price_sale : copy.price));
      copy.stock = variant.stock === null || variant.stock === undefined ? null : Number(variant.stock);
      copy.weight_gram = variant.weight_gram === null || variant.weight_gram === undefined ? null : Number(variant.weight_gram);
      copy.sort_order = Number(variant.sort_order ?? 0);
      copy.is_default = Number(variant.is_default ?? 0);
      copy.status = variant.status;
      return copy;
    });
  } else {
    normalized.variants = [];
  }
  
  return normalized;
}

/* --------- SEO động --------- */
export async function generateMetadata({ params }) {
  const { id } = await params;
  
  // Detect nếu là slug (chứa chữ cái) thì dùng API slug, còn lại dùng API id
  const isSlug = /[a-zA-Z]/.test(id);
  const url = isSlug 
    ? `${API_BASE}/api/v1/products/slug/${encodeURIComponent(id)}`
    : `${API_BASE}/api/v1/products/${id}`;
    
  const p = await fetchJSON(url);
  if (!p) return { title: "Sản phẩm | Dola Bakery" };

  const title = `${p.name} | Dola Bakery`;
  
  // Xử lý description cho SEO - ưu tiên description, fallback về content_html
  let desc = p.description;
  if (!desc && p.content_html) {
    // Loại bỏ HTML tags để lấy text thuần cho SEO
    desc = p.content_html.replace(/<[^>]*>/g, '').slice(0, 180);
  }
  if (!desc) {
    desc = `Thưởng thức ${p.name} thơm ngon, tươi mới từ Dola Bakery.`;
  } else {
    desc = desc.slice(0, 180);
  }
  
  const img = normImg(p.thumbnail || p.image || "/slide1.jpg");

  const siteUrl = (process.env.NEXT_PUBLIC_SITE_URL || "").replace(/\/+$/, "");
  // Ưu tiên slug nếu có để SEO tốt hơn, fallback về id (param từ route)
  const canonicalSlug = p.slug || id;
  return {
    title,
    description: desc,
    alternates: { canonical: `${siteUrl}/product/${canonicalSlug}` },
    openGraph: {
      title,
      description: desc,
      images: [{ url: img, width: 800, height: 600, alt: p.name }],
      url: `${siteUrl}/product/${canonicalSlug}`,
    },
    twitter: { card: "summary_large_image", title, description: desc, images: [img] },
  };
}

/* --------- Trang chi tiết --------- */
export default async function ProductPage({ params }) {
  const { id } = await params;
  
  // Detect nếu là slug (chứa chữ cái) thì dùng API slug, còn lại dùng API id
  const isSlug = /[a-zA-Z]/.test(id);
  const url = isSlug 
    ? `${API_BASE}/api/v1/products/slug/${encodeURIComponent(id)}`
    : `${API_BASE}/api/v1/products/${id}`;
    
  const rawProduct = await fetchJSON(url);
  if (!rawProduct) notFound();
  
  console.log("=== DEBUG PRODUCT DATA ===");
  console.log("Product ID:", id);
  console.log("Raw product from API:", rawProduct);
  console.log("Raw thumbnail:", rawProduct.thumbnail);
  console.log("Raw image:", rawProduct.image);
  console.log("Raw images array:", rawProduct.images);
  
  // Normalize product data for SSR
  const product = normalizeProductForSSR(rawProduct);
  
  console.log("Normalized product:", product);
  console.log("Normalized thumbnail:", product.thumbnail);
  console.log("Normalized image:", product.image);
  console.log("Normalized images array:", product.images);
  console.log("Product description:", product.description);
  console.log("Product content:", product.content);
  console.log("Product content_html:", product.content_html);
  console.log("=== END DEBUG ===");

  const variants = Array.isArray(product.variants) ? product.variants : [];
  const sortedVariants = [...variants].sort((a, b) => {
    const aDefault = Number(a?.is_default ?? 0);
    const bDefault = Number(b?.is_default ?? 0);
    if (bDefault - aDefault !== 0) return bDefault - aDefault;
    const aSort = Number(a?.sort_order ?? 0);
    const bSort = Number(b?.sort_order ?? 0);
    if (aSort !== bSort) return aSort - bSort;
    return Number(a?.id ?? 0) - Number(b?.id ?? 0);
  });
  const variantsForUI = sortedVariants.filter(
    (v) => v && (v.status === undefined || v.status === null || Number(v.status) === 1)
  );
  const primaryVariant = variantsForUI.find((v) => Number(v.is_default ?? 0) === 1) ?? variantsForUI[0] ?? null;

  // ✅ Tính giá: ưu tiên product_sale active từ bảng product_sale, sau đó mới đến price_sale từ bảng product
  const priceBuy = primaryVariant ? Number(primaryVariant.price ?? product.price_buy ?? 0) : Number(product.price_buy ?? 0);
  
  // Kiểm tra product_sale active (đã được normalize trong normalizeProductForSSR)
  let saleCandidate = null;
  if (product.product_sale && isSaleActive(product.product_sale)) {
    saleCandidate = Number(product.product_sale.price_sale ?? 0);
  } else {
    // Fallback về price_sale từ bảng product hoặc variant
    saleCandidate = primaryVariant ? Number(primaryVariant.price_sale ?? 0) : Number(product.price_sale ?? 0);
  }
  
  const priceSale = saleCandidate > 0 && saleCandidate < priceBuy ? saleCandidate : null;

  // Đảm bảo mọi phần tử gallery đều là URL hợp lệ
  const gallery = [
    product.thumbnail || product.image || "/slide1.jpg",
    ...(Array.isArray(product.images) ? product.images : []),
  ]
    .filter(Boolean)
    .map(img => normImg(typeof img === "string" ? img : img.image || img.url || img.src || ""));

  let related = [];
  if (product?.category?.slug) {
    const rel = await fetchJSON(
      `${API_BASE}/api/v1/products?category_slug=${encodeURIComponent(
        product.category.slug
      )}&status=1&per_page=12&sort=created_desc`
    );
    related = Array.isArray(rel?.data)
      ? rel.data.filter((x) => x.id !== product.id).map(normalizeProductForSSR)
      : [];
  }

  const siteUrl = (process.env.NEXT_PUBLIC_SITE_URL || "").replace(/\/+$/, "");
  // Ưu tiên slug nếu có để SEO tốt hơn, fallback về id
  const productSlug = product.slug || id;
  const productLd = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    image: gallery.length
      ? gallery
      : [normImg(product.thumbnail || product.image || "/slide1.jpg")],
    description: product.description || "",
    sku: String(product.id),
    category: product?.category?.name || "Bánh - Đồ nướng",
    offers: {
      "@type": "Offer",
      priceCurrency: "VND",
      price: String(priceSale || priceBuy || 0),
      availability: "https://schema.org/InStock",
      url: `${siteUrl}/product/${productSlug}`,
    },
  };
  const breadcrumbsLd = {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: [
      { "@type": "ListItem", position: 1, name: "Trang chủ", item: `${siteUrl}/` },
      { "@type": "ListItem", position: 2, name: "Sản phẩm", item: `${siteUrl}/product` },
      {
        "@type": "ListItem",
        position: 3,
        name: product.name,
        item: `${siteUrl}/product/${productSlug}`,
      },
    ],
  };

  return (
    <main className="container mx-auto px-4 py-8">
      <Script id="ld-product" type="application/ld+json">
        {JSON.stringify(productLd)}
      </Script>
      <Script id="ld-breadcrumbs" type="application/ld+json">
        {JSON.stringify(breadcrumbsLd)}
      </Script>

      <div className="grid md:grid-cols-12 gap-8 items-start">
        {/* LEFT: Gallery */}
        <div className="md:col-span-5">
          <Gallery images={gallery} name={product.name} />
        </div>

        {/* RIGHT: Info */}
        <div className="md:col-span-7">
          <div className="flex items-start justify-between mb-1">
            <h1 className="text-[22px] md:text-3xl font-extrabold text-amber-700 flex-1">
              {product.name}
            </h1>
            <ProductFavoriteButton productId={product.id} className="rounded-full bg-white/90 p-2 hover:scale-110 transition-transform ml-4 shadow" />
          </div>

          <div className="flex flex-wrap items-center gap-2 md:gap-3 mb-4 text-sm text-gray-700">
            {product?.category?.name && (
              <>
                <span className="opacity-70">Loại:</span>
                <Link
                  href={`/product?category=${product.category.slug}`}
                  className="text-amber-700 hover:underline font-medium"
                >
                  {product.category.name}
                </Link>
                <span className="opacity-60">|</span>
              </>
            )}
            <span>
              Tình trạng:{" "}
              {product?.available_quantity > 0 ? (
                <b className="text-green-600">
                  Còn hàng ({product.available_quantity} sản phẩm)
                </b>
              ) : (
                <b className="text-red-600">Hết hàng</b>
              )}
            </span>
          </div>

          {/* Giá */}
          <VariantPricingBox
            apiBase={API_BASE}
            productId={product.id}
            productName={product.name}
            productThumb={product.thumbnail || product.image || "/slide1.jpg"}
            variants={variantsForUI}
            fallbackPriceBuy={priceBuy}
            fallbackPriceSale={priceSale ?? 0}
            productAvailableQuantity={product.available_quantity || 0}
            productInStock={product.is_in_stock !== false}
          />

          {/* Mã giảm giá cho sản phẩm này */}
          <ProductCoupons productId={product.id} categoryId={product.category?.id} />

          {/* Chính sách */}
          <div className="grid sm:grid-cols-2 gap-3 mb-6 text-sm">
            <div className="p-3 rounded-lg border bg-amber-50 flex gap-3">
              <span>💳</span>
              <div>
                <b>Khuyến mãi</b>
                <div>Áp dụng theo ngành hàng – thêm mã khi thanh toán.</div>
              </div>
            </div>
            <div className="p-3 rounded-lg border bg-amber-50 flex gap-3">
              <span>🚚</span>
              <div>
                <b>Miễn phí vận chuyển</b>
                <div>Áp dụng khu vực & đơn từ 300.000đ</div>
              </div>
            </div>
            <div className="p-3 rounded-lg border bg-amber-50 flex gap-3">
              <span>🛠️</span>
              <div>
                <b>Đổi trả dễ</b>
                <div>Đổi ngay nếu lỗi vận chuyển hoặc sai mô tả.</div>
              </div>
            </div>
            <div className="p-3 rounded-lg border bg-amber-50 flex gap-3">
              <span>☎️</span>
              <div>
                <b>Hỗ trợ nhanh</b>
                <div>Hotline 1900 6750</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs mô tả */}
      <section className="mt-8">
        <div className="flex gap-6 border-b mb-4">
          <button className="py-2 font-semibold border-b-2 border-amber-600 text-amber-700">
            Mô tả ngắn
          </button>
          <button className="py-2 text-gray-500 hover:text-amber-700">
            Mô tả chi tiết
          </button>
        </div>

        {/* Mô tả ngắn */}
        <div className="prose max-w-none prose-p:leading-7">
          {product.description ? (
            <p className="text-gray-700 whitespace-pre-line">
              {product.description}
            </p>
          ) : (
            <p className="text-gray-500">Chưa có mô tả ngắn.</p>
          )}
        </div>

        {/* Mô tả chi tiết */}
        <div className="prose max-w-none mt-6 border-t pt-4">
          {(() => {
            // Ưu tiên content_html từ API, fallback về content nếu cần
            let contentToShow = product.content_html || product.content;
            
            // Nếu content là JSON string, thử parse lấy html
            if (contentToShow && typeof contentToShow === 'string') {
              try {
                const parsed = JSON.parse(contentToShow);
                if (parsed.html) {
                  contentToShow = parsed.html;
                }
              } catch (e) {
                // Nếu không parse được JSON, giữ nguyên contentToShow
              }
            }
            
            return contentToShow ? (
              <div
                className="text-gray-700 prose-p:leading-7"
                dangerouslySetInnerHTML={{ __html: contentToShow }}
              />
            ) : (
              <p className="text-gray-500">Chưa có mô tả chi tiết.</p>
            );
          })()}
        </div>
      </section>

      {/* Đánh giá sản phẩm */}
      <ProductReviewsSection product={product} />

      {/* Sản phẩm liên quan */}
      {related.length > 0 && (
        <section className="mt-14 mb-10 py-8 bg-white border-y border-amber-200">
          <div className="max-w-7xl mx-auto px-4">
            {/* Title with decorative element */}
            <div className="text-center mb-6">
              <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-2 tracking-wide relative inline-block" style={{ fontFamily: 'Georgia, serif' }}>
                Sản phẩm liên quan
                <div className="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-32 h-1 bg-gradient-to-r from-transparent via-amber-400 to-transparent"></div>
              </h2>
            </div>

            {/* Products Grid - 5 columns */}
            <div
              className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4"
              role="list"
            >
              {related.map((it) => (
                <RelatedProductCard key={`related-${it.id}`} product={it} />
              ))}
            </div>
          </div>
        </section>
      )}

      {/* Đã xem */}
      <RecentlyViewed me={product} />
    </main>
  );
}
