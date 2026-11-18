import { Helmet } from "react-helmet-async";

interface SEOProps {
  title?: string;
  description?: string;
  canonical?: string;
  image?: string;
  type?: "website" | "article";
  noindex?: boolean;
  jsonLd?: Record<string, unknown> | Record<string, unknown>[];
}

const SITE_NAME = "VetRank";
const BASE_URL = "https://vetrank.com.br";
const DEFAULT_IMAGE = `${BASE_URL}/og-image.png`;
const DEFAULT_DESC =
  "Plataforma de preparação para Medicina Veterinária. Banco de questões, simulados e gamificação para ENADE, Residência, ENARE e provas de graduação.";

export default function SEO({
  title,
  description,
  canonical,
  image,
  type = "website",
  noindex = false,
  jsonLd,
}: SEOProps) {
  const fullTitle = title
    ? `${title} | ${SITE_NAME}`
    : `${SITE_NAME} — Prepare-se para Medicina Veterinária`;
  const desc = description ?? DEFAULT_DESC;
  const img = image ?? DEFAULT_IMAGE;
  const url = canonical ? `${BASE_URL}${canonical}` : BASE_URL;
  const schemas = jsonLd ? (Array.isArray(jsonLd) ? jsonLd : [jsonLd]) : [];

  return (
    <Helmet>
      <title>{fullTitle}</title>
      <meta name="description" content={desc} />
      <link rel="canonical" href={url} />
      {noindex && <meta name="robots" content="noindex, nofollow" />}

      {/* Open Graph */}
      <meta property="og:site_name" content={SITE_NAME} />
      <meta property="og:title" content={fullTitle} />
      <meta property="og:description" content={desc} />
      <meta property="og:url" content={url} />
      <meta property="og:type" content={type} />
      <meta property="og:image" content={img} />
      <meta property="og:locale" content="pt_BR" />

      {/* Twitter */}
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={fullTitle} />
      <meta name="twitter:description" content={desc} />
      <meta name="twitter:image" content={img} />

      {/* JSON-LD */}
      {schemas.map((schema, i) => (
        <script key={i} type="application/ld+json">
          {JSON.stringify(schema)}
        </script>
      ))}
    </Helmet>
  );
}
