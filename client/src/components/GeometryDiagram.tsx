import React, { type CSSProperties } from "react";

export type GeometryShape = "rectangle" | "triangle" | "circle" | "angle";
export type GeometryDiagramSpec = {
  shape: GeometryShape;
  dimensions: Record<string, string>;
  labels?: Record<string, string>;
};

const svgStyle: CSSProperties = { maxWidth: "100%", height: "auto" };

function DimensionText({ x, y, children }: { x: number; y: number; children: string }) {
  return <text x={x} y={y} textAnchor="middle" className="geometry-dimension">{children}</text>;
}

export function GeometryDiagram({ spec }: { spec: GeometryDiagramSpec }) {
  const labels = spec.labels || {};
  const title = labels.title || `شكل هندسي ${spec.shape}`;
  return (
    <figure className="geometry-diagram" role="img" aria-label={title} dir="ltr">
      <svg viewBox="0 0 360 220" style={svgStyle} aria-hidden="true">
        <defs><marker id="geometry-arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor" /></marker></defs>
        {spec.shape === "rectangle" && <>
          <rect x="80" y="50" width="200" height="110" className="geometry-shape" />
          <line x1="80" y1="178" x2="280" y2="178" className="geometry-dimension-line" markerStart="url(#geometry-arrow)" markerEnd="url(#geometry-arrow)" />
          <line x1="305" y1="50" x2="305" y2="160" className="geometry-dimension-line" markerStart="url(#geometry-arrow)" markerEnd="url(#geometry-arrow)" />
          <DimensionText x={180} y={198}>{spec.dimensions.width || "الطول"}</DimensionText>
          <DimensionText x={330} y={108}>{spec.dimensions.height || "العرض"}</DimensionText>
        </>}
        {spec.shape === "triangle" && <>
          <polygon points="180,35 70,170 290,170" className="geometry-shape" />
          <line x1="70" y1="188" x2="290" y2="188" className="geometry-dimension-line" markerStart="url(#geometry-arrow)" markerEnd="url(#geometry-arrow)" />
          <DimensionText x={180} y={210}>{spec.dimensions.base || "القاعدة"}</DimensionText>
          <DimensionText x={190} y={95}>{spec.dimensions.height || "الارتفاع"}</DimensionText>
        </>}
        {spec.shape === "circle" && <>
          <circle cx="180" cy="110" r="70" className="geometry-shape" />
          <line x1="180" y1="110" x2="250" y2="110" className="geometry-dimension-line" markerEnd="url(#geometry-arrow)" />
          <DimensionText x={215} y={98}>{spec.dimensions.radius || "نق"}</DimensionText>
        </>}
        {spec.shape === "angle" && <>
          <path d="M 80 170 L 180 170 L 240 70" className="geometry-shape geometry-angle" />
          <path d="M 140 170 A 40 40 0 0 0 204 136" className="geometry-angle-arc" />
          <DimensionText x={176} y={155}>{spec.dimensions.angle || "الزاوية"}</DimensionText>
        </>}
      </svg>
      <figcaption>{title}</figcaption>
    </figure>
  );
}

export function isGeometryDiagram(value: unknown): value is GeometryDiagramSpec {
  return Boolean(value && typeof value === "object" && "shape" in value && "dimensions" in value);
}
