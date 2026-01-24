"use client";
import React from "react";

const features = [
  {
    title: "Miễn phí vận chuyển",
    desc: "Áp dụng free ship cho đơn nội thành đủ điều kiện, từ 300 nghìn",
    icon: (
      <svg viewBox="0 0 24 24" className="w-8 h-8" aria-hidden="true">
        <path d="M3 7h11v8H3zM14 9h4l3 3v3h-7z" fill="currentColor" opacity=".2" />
        <path
          d="M3 7h11v8H3zM14 9h4l3 3v3h-7zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM14 15H3m18 0h-2M17 9v6"
          stroke="currentColor"
          strokeWidth="1.5"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    ),
  },
  {
    title: "Dễ đặt & sử dụng",
    desc: "Giao diện rõ ràng, đặt bánh trong vài phút, không cần yêu cầu",
    icon: (
      <svg viewBox="0 0 24 24" className="w-8 h-8" aria-hidden="true">
        <path d="M4 5h16v14H4z" fill="currentColor" opacity=".2" />
        <path
          d="M4 5h16v14H4zM8 3v4M16 3v4M7 10h10M7 14h6"
          stroke="currentColor"
          strokeWidth="1.5"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    ),
  },
  {
    title: "Hỗ trợ nhanh chóng",
    desc: "Hotline 09006750 phản hồi tức thì, hỗ trợ ngay",
    icon: (
      <svg viewBox="0 0 24 24" className="w-8 h-8" aria-hidden="true">
        <path d="M4 5h16v10H7l-3 3z" fill="currentColor" opacity=".2" />
        <path
          d="M4 5h16v10H7l-3 3zM8 10h8M8 7h10"
          stroke="currentColor"
          strokeWidth="1.5"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    ),
  },
  {
    title: "Thanh toán đa dạng",
    desc: "Tiền mặt, Chuyển khoản, Momo, VNPAY, Visa, Chuyển khoản Napas",
    icon: (
      <svg viewBox="0 0 24 24" className="w-8 h-8" aria-hidden="true">
        <path d="M3 7h18v10H3z" fill="currentColor" opacity=".2" />
        <path
          d="M3 7h18v10H3zM7 11h6M7 14h4M15 14h2"
          stroke="currentColor"
          strokeWidth="1.5"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    ),
  },
];

export default function FeatureStrip() {
  return (
    <section className="relative py-6 bg-[#faf7f2] border-y border-amber-200" aria-label="Key Features">
      <div className="mx-auto max-w-7xl px-4">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {features.map((f, i) => (
            <div
              key={i}
              className="flex items-center gap-3 bg-gray-50 rounded-lg p-4 border border-amber-200 hover:bg-amber-50 transition-colors"
            >
              <div className="flex-shrink-0 text-amber-700">
                {f.icon}
              </div>
              <div className="min-w-0">
                <h3 className="font-bold text-sm text-gray-900 leading-tight">
                  {f.title}
                </h3>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}