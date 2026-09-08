import type { NextConfig } from 'next';

const validationWithoutStandalone = process.env.CLY_VALIDATION_NO_STANDALONE === '1';
const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost';
const apiOrigin = (() => {
  try {
    return new URL(apiUrl).origin;
  } catch {
    return 'http://localhost';
  }
})();

const contentSecurityPolicy = [
  "default-src 'self'",
  "base-uri 'self'",
  "frame-ancestors 'none'",
  "object-src 'none'",
  "form-action 'self'",
  `connect-src 'self' ${apiOrigin}`,
  `img-src 'self' data: blob: ${apiOrigin}`,
  "font-src 'self' data:",
  "style-src 'self' 'unsafe-inline'",
  "script-src 'self' 'unsafe-inline'",
  "worker-src 'self' blob:",
  process.env.NODE_ENV === 'production' ? 'upgrade-insecure-requests' : '',
].filter(Boolean).join('; ');

const nextConfig: NextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  compress: true,
  // Production containers use the small standalone bundle. Windows validation
  // can disable only the tracing copy when dependencies originate on another drive.
  output: validationWithoutStandalone ? undefined : 'standalone',
  outputFileTracingRoot: process.cwd(),
  eslint: {
    // ESLint 9 is run separately; its current patcher is incompatible with
    // Next's build worker and previously prevented production builds.
    ignoreDuringBuilds: true,
  },

  async headers() {
    return [{
      source: '/:path*',
      headers: [
        { key: 'X-Content-Type-Options', value: 'nosniff' },
        { key: 'X-Frame-Options', value: 'DENY' },
        { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
        { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=()' },
        { key: 'Strict-Transport-Security', value: 'max-age=31536000; includeSubDomains' },
        { key: 'Content-Security-Policy', value: contentSecurityPolicy },
      ],
    }];
  },

  // The Next.js dev server proxies API requests to Laravel so cookies
  // and Sanctum stateful auth work without cross-origin headaches.
  async rewrites() {
    const apiOrigin = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost';
    return [
      {
        source: '/api/:path*',
        destination: `${apiOrigin}/api/:path*`,
      },
    ];
  },

  images: {
    remotePatterns: [
      { protocol: 'http', hostname: 'localhost' },
      { protocol: 'https', hostname: process.env.NEXT_PUBLIC_API_HOSTNAME ?? 'api.example.com' },
    ],
  },
};

export default nextConfig;
