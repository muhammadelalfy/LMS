# Animated Mathematics Background Verification

The `/admin/login` authentication surface was checked at desktop `1280×720` and mobile `375×812` viewports. The desktop composition shows the emerald 3D orb, orbit lines, geometric wireframe, floating formulas, and copper sparkles around a readable glass-like authentication card. The mobile composition keeps the card within the viewport, stacks portal controls cleanly, and preserves the scene as a secondary layer without clipping the form.

The implementation uses CSS animation only, with `prefers-reduced-motion: reduce` disabling non-essential motion. TypeScript and the production build pass after integration.

## Accessibility review

The background is decorative and marked `aria-hidden`, so it does not add noise to assistive technology. Authentication and system content remain above the scene using explicit stacking order and opaque/translucent content surfaces. The authentication card, labels, controls, and primary action retain readable dark-on-light contrast, while the dark system sidebar remains high contrast against the animated scene. Visible `:focus-visible` outlines were added to authentication controls, navigation, logout, and primary actions. The `prefers-reduced-motion` media query disables the scene animations while retaining the static geometry and color treatment.

The authenticated dashboard shell was also reviewed structurally: `MathUniverseBackground` is mounted as the first layer inside `.live-shell`, while the sidebar, header, and main content are positioned above it. The `/admin` path is not a registered route in this application, so screenshot verification uses the registered `/admin/login` entry point; the authenticated shell uses the same shared background component and stacking rules.
