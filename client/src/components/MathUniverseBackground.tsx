import type { CSSProperties } from "react";

type MathUniverseBackgroundProps = {
  tone?: "auth" | "system";
};

const formulas = ["∑ n²", "πr²", "f(x)", "∫ dx", "a²+b²", "Δy/Δx"];

export default function MathUniverseBackground({ tone = "system" }: MathUniverseBackgroundProps) {
  return (
    <div className={`math-universe math-universe--${tone}`} aria-hidden="true">
      <div className="math-universe__wash" />
      <div className="math-universe__grid" />
      <div className="math-universe__orb math-universe__orb--one" />
      <div className="math-universe__orb math-universe__orb--two" />
      <div className="math-universe__orbit math-universe__orbit--one"><span /></div>
      <div className="math-universe__orbit math-universe__orbit--two"><span /></div>
      <div className="math-universe__orbit math-universe__orbit--three"><span /></div>
      <div className="math-universe__cube"><i /><i /><i /><i /><i /><i /></div>
      <div className="math-universe__pyramid"><i /><i /><i /><i /></div>
      <div className="math-universe__tetrahedron"><i /><b /><em /></div>
      <div className="math-universe__formula-field">
        {formulas.map((formula, index) => <span key={formula} style={{ "--formula-index": index } as CSSProperties}>{formula}</span>)}
      </div>
      <div className="math-universe__sparkles"><i /><i /><i /><i /><i /></div>
    </div>
  );
}
