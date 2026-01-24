"use client";

import { useState } from "react";
import Link from "next/link";
import { syncLocalFavoritesToServer } from "@/utils/favoritesService";

const API_BASE = (process.env.NEXT_PUBLIC_API_BASE || "http://127.0.0.1:8000").replace(/\/+$/, "");
const API_V1 = API_BASE + "/api/v1";
const TOKEN_KEY = "auth_token";
const PROFILE_KEY = "dola_profile";

// utils
function setToken(token) { 
  try { 
    localStorage.setItem(TOKEN_KEY, token); 
  } catch (e) {} 
}

function clearToken() { 
  try { 
    localStorage.removeItem(TOKEN_KEY); 
    localStorage.removeItem(PROFILE_KEY); 
  } catch (e) {} 
}

function setProfile(p) { 
  try { 
    localStorage.setItem(PROFILE_KEY, JSON.stringify(p)); 
  } catch (e) {} 
}

function getNextFromUrl() {
  try {
    const u = new URL(window.location.href);
    const nxt = u.searchParams.get("next");
    if (nxt && /^\/[^\s]*$/.test(nxt)) return nxt;
  } catch (e) {}
  return "/profile";
}

export default function LoginForm() {
  const [loading, setLoading] = useState(false);
  const [showPass, setShowPass] = useState(false);
  const [err, setErr] = useState("");
  const [success, setSuccess] = useState("");
  const [fieldErrors, setFieldErrors] = useState({});

  async function onSubmit(e) {
    e.preventDefault();
    setErr(""); 
    setSuccess("");
    setFieldErrors({});

    const fd = new FormData(e.currentTarget);
    const identifier = String(fd.get("identifier") || "").trim();
    const password   = String(fd.get("password")   || "").trim();

    // Client-side validation
    if (!identifier) {
      setFieldErrors({ identifier: "Vui lòng nhập Email / SĐT / Tên đăng nhập." });
      return;
    }
    if (password.length < 6) {
      setFieldErrors({ password: "Mật khẩu tối thiểu 6 ký tự." });
      return;
    }

    setLoading(true);
    try {
      const res = await fetch(`${API_V1}/auth/login`, {
        method: "POST",
        headers: { 
          "Content-Type": "application/json", 
          Accept: "application/json" 
        },
        body: JSON.stringify({ identifier, password }),
      });
      
      const json = await res.json().catch(() => ({}));

      if (!res.ok) {
        const message = json?.message || "Đăng nhập thất bại.";
        setErr(message);
        
        // Parse field errors if any
        if (json?.errors) {
          const parsedErrors = {};
          Object.keys(json.errors).forEach(key => {
            parsedErrors[key] = Array.isArray(json.errors[key]) ? json.errors[key][0] : json.errors[key];
          });
          setFieldErrors(parsedErrors);
        }
        return;
      }

      const token = json?.token;
      if (!token) {
        setErr("Phản hồi không hợp lệ (không có token).");
        return;
      }

      setToken(token);
      setSuccess("✅ Đăng nhập thành công! Đang chuyển hướng...");

      // 2) ME
      try {
        const meRes = await fetch(`${API_V1}/auth/me`, {
          headers: { 
            Accept: "application/json", 
            Authorization: `Bearer ${token}` 
          },
        });
        const me = await meRes.json().catch(() => ({}));
        
        if (!meRes.ok || !me?.user) {
          clearToken();
          setErr(me?.message || "Không lấy được thông tin tài khoản.");
          return;
        }

        // Lưu hồ sơ rút gọn vào localStorage
        setProfile({
          id: me.user.id,
          name: me.customer?.name || me.user.name,
          email: me.user.email,
          avatar: me.user.avatar || "/logo.png",
          phone: me.customer?.phone || me.user.phone,
          address: me.customer?.address,
          birthday: me.customer?.birthday,
          joined: me.user.created_at,
          roles: me.user.roles,
        });

        // Thông báo nếu chưa xác thực email
        if (json?.needs_verify) {
          setTimeout(() => {
            alert("⚠️ Tài khoản của bạn chưa được xác thực email. Vui lòng kiểm tra hộp thư.");
          }, 500);
        }

        // Phát event để Header/Client khác cập nhật UI
        try { 
          window.dispatchEvent(new Event("auth-changed")); 
        } catch (e) {}

        // Đồng bộ favorites từ localStorage lên server (nếu có)
        try {
          const syncResult = await syncLocalFavoritesToServer();
          if (syncResult.success && syncResult.successCount > 0) {
            console.log(`Đã đồng bộ ${syncResult.successCount} sản phẩm yêu thích`);
          }
        } catch (syncErr) {
          console.error("Lỗi khi đồng bộ favorites:", syncErr);
          // Không block redirect nếu sync lỗi
        }

        // 3) Điều hướng
        const next = getNextFromUrl();
        setTimeout(() => {
          window.location.replace(next);
        }, 1500);
      } catch (e) {
        console.error("Error fetching profile:", e);
        // Profile fetch failed but login succeeded, still redirect
        const next = getNextFromUrl();
        setTimeout(() => {
          window.location.replace(next);
        }, 1500);
      }
    } catch (e2) {
      setErr("Lỗi kết nối. Vui lòng kiểm tra kết nối mạng và thử lại.");
      console.error(e2);
    } finally {
      setLoading(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      {/* Success Message */}
      {success && (
        <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
          {success}
        </div>
      )}

      {/* Error Message */}
      {err && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          <div className="flex items-start gap-2">
            <span className="text-lg">⚠️</span>
            <div>
              <p className="font-semibold">Đăng nhập thất bại</p>
              <p>{err}</p>
            </div>
          </div>
        </div>
      )}

      {/* Identifier Field */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Email / Số điện thoại / Tên đăng nhập *
        </label>
        <input
          name="identifier"
          className={`w-full px-4 py-3 rounded-lg border ${
            fieldErrors.identifier ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-orange-500'
          } focus:outline-none focus:ring-2`}
          placeholder="vd: alice@gmail.com hoặc 0901xxxxxx"
          autoComplete="username"
          onChange={() => {
            if (fieldErrors.identifier) {
              setFieldErrors({ ...fieldErrors, identifier: null });
            }
            if (err) setErr("");
          }}
        />
        {fieldErrors.identifier && (
          <p className="mt-1 text-sm text-red-600">{fieldErrors.identifier}</p>
        )}
      </div>

      {/* Password Field */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Mật khẩu *
        </label>
        <div className="relative">
          <input
            name="password"
            type={showPass ? "text" : "password"}
            className={`w-full px-4 py-3 pr-12 rounded-lg border ${
              fieldErrors.password ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-orange-500'
            } focus:outline-none focus:ring-2`}
            placeholder="Nhập mật khẩu của bạn"
            required
            autoComplete="current-password"
            onChange={() => {
              if (fieldErrors.password) {
                setFieldErrors({ ...fieldErrors, password: null });
              }
              if (err) setErr("");
            }}
          />
          <button
            type="button"
            onClick={() => setShowPass(s => !s)}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
            aria-label="Hiện/ẩn mật khẩu"
          >
            {showPass ? "🙈" : "👁️"}
          </button>
        </div>
        {fieldErrors.password && (
          <p className="mt-1 text-sm text-red-600">{fieldErrors.password}</p>
        )}
      </div>

      {/* Submit Button */}
      <button
        type="submit"
        disabled={loading}
        className="w-full bg-orange-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-orange-600 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
      >
        {loading ? "⏳ Đang đăng nhập…" : "🚀 Đăng nhập"}
      </button>

      {/* Forgot Password Link */}
      <div className="text-center">
        <Link href="/forgot-password" className="text-sm text-orange-600 hover:underline">
          Quên mật khẩu?
        </Link>
      </div>
    </form>
  );
}
