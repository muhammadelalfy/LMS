import React from "react";
import { describe, expect, it } from "vitest";
import { GeometryDiagram, isGeometryDiagram } from "./GeometryDiagram";
import { renderToStaticMarkup } from "react-dom/server";

describe("GeometryDiagram", () => {
  it("recognizes a dimensioned diagram contract", () => {
    expect(isGeometryDiagram({ shape: "rectangle", dimensions: { width: "٦ سم", height: "٤ سم" } })).toBe(true);
    expect(isGeometryDiagram(["not", "a", "diagram"])).toBe(false);
  });

  it("renders a labeled SVG shape for browser and PDF capture", () => {
    const html = renderToStaticMarkup(<GeometryDiagram spec={{ shape: "triangle", dimensions: { base: "٨ سم", height: "٥ سم" }, labels: { title: "مثلث" } }} />);
    expect(html).toContain("مثلث");
    expect(html).toContain("٨ سم");
    expect(html).toContain("geometry-shape");
  });
});
