"use client";

import Image from "next/image";
import { useState } from "react";

const API_BASE = (process.env.NEXT_PUBLIC_API_BASE || "http://127.0.0.1:8000").replace(/\/+$/, "");

function normImg(src) {
  if (!src) return "/slide1.jpg";
  const s = typeof src === "string" ? src : src.image || src.url || src.src || "";
  if (!s) return "/slide1.jpg";
  
  console.log("Gallery normImg input:", s);
  
  if (s.startsWith("http")) return s;
  if (s.startsWith("/")) return `${API_BASE}/api/v1${s}`;
  
  // Xử lý URL hình ảnh để thêm /api/v1 nếu cần
  if (s.includes("storage/")) {
    const result = `${API_BASE}/api/v1/${s.replace(/^\/+/, "")}`;
    console.log("Gallery normImg storage result:", result);
    return result;
  }
  
  console.log("Gallery normImg fallback to default");
  return "/slide1.jpg";
}

export default function Gallery({ images = [], name = "" }) {
  const [active, setActive] = useState(0);

  if (!images || images.length === 0) {
    images = ["/slide1.jpg"];
  }

  // Normalize all images
  const normalizedImages = images.map(img => normImg(img));

  return (
    <div className="space-y-3">
      {/* Ảnh chính */}
      <div className="relative w-full h-96 border rounded-lg overflow-hidden">
        <Image
          src={normalizedImages[active]}
          alt={name}
          fill
          className="object-cover"
          sizes="(max-width: 768px) 100vw, 50vw"
        />
      </div>

      {/* Thumbnail */}
      <div className="grid grid-cols-5 gap-2">
        {normalizedImages.map((src, i) => (
          <button
            key={i}
            onClick={() => setActive(i)}
            className={`relative h-20 border rounded overflow-hidden ${
              i === active ? "ring-2 ring-amber-500" : ""
            }`}
          >
            <Image
              src={src}
              alt={`${name} ${i + 1}`}
              fill
              className="object-cover"
              sizes="20vw"
            />
          </button>
        ))}
      </div>
    </div>
  );
}
