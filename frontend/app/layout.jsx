import './globals.css'
import Script from 'next/script'

export const metadata = {
  title: 'VeriBits - Digital Trust Verification Platform',
  description: 'VeriBits - Enterprise-grade digital trust verification for files, emails, and transactions. Hash verification, file integrity checking, email authentication, and blockchain-anchored proof of existence.',
  keywords: 'file verification, hash lookup, email verification, digital trust, file integrity, blockchain verification, proof of existence, document verification, SHA256 lookup, MD5 lookup, malware detection',
  authors: [{ name: 'VeriBits' }],
  robots: 'index, follow',
  openGraph: {
    type: 'website',
    siteName: 'VeriBits',
    title: 'VeriBits - Digital Trust Verification Platform',
    description: 'Enterprise-grade digital trust verification for files, emails, and transactions. Hash verification, file integrity, and blockchain-anchored proof of existence.',
    url: 'https://veribits.com',
    images: [{ url: 'https://veribits.com/og-image.png', width: 1200, height: 630, alt: 'VeriBits' }],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'VeriBits - Digital Trust Verification',
    description: 'Enterprise file verification, hash lookup, email authentication, and blockchain-anchored proof of existence.',
    images: ['https://veribits.com/twitter-image.png'],
  },
  alternates: {
    canonical: 'https://veribits.com',
  },
}

export default function RootLayout({ children }) {
  return (
    <html lang="en" className="h-full">
      <head>
        {/* DNS Science Analytics */}
        <Script
          src="https://www.dnsscience.io/static/js/analytics_track.js"
          data-token="dsa_f27457f6331c764b8a53dadf4b5aff75a1f1dfdc171c6bce977666dd"
          strategy="afterInteractive"
        />
      </head>
      <body className="h-full">
        {children}
      </body>
    </html>
  )
}