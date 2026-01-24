/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    domains: ["127.0.0.1", "localhost", "bizweb.dktcdn.net"],
    remotePatterns: [
      { protocol: "http", hostname: "127.0.0.1", port: "8000", pathname: "/**" },
      { protocol: "http", hostname: "localhost",  port: "8000", pathname: "/**" },
      { protocol: "https", hostname: "bizweb.dktcdn.net", pathname: "/**" },
    ],
    formats: ["image/avif", "image/webp"],
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],
    minimumCacheTTL: 60,
  },
  compress: true,
  poweredByHeader: false,
  reactStrictMode: true,
  swcMinify: true,
  experimental: {
    optimizeCss: true,
  },
  async rewrites() {
    return [
      { source: "/api/:path*",     destination: "http://127.0.0.1:8000/api/:path*" },
      { source: "/uploads/:path*", destination: "http://127.0.0.1:8000/uploads/:path*" },
      { source: "/storage/:path*", destination: "http://127.0.0.1:8000/storage/:path*" },
    ];
  },
};
export default nextConfig;
