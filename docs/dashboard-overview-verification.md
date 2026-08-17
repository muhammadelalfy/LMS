# Dashboard Overview and QR Tab Verification

The authenticated Arabic dashboard now opens on a real-data overview. The verified desktop session displayed four KPIs—student count, attendance rate, exam average, and completed payments—alongside attendance bars, exam performance bars, a payment completion ring, and a learner-flow diagram. Values were loaded from the Laravel API data already present in the dashboard rather than fabricated fixtures.

The sidebar now includes a separate `مسح QR` tab. Opening it removes the QR scanner from the overview and presents the existing student selector, QR-card generation, camera scanner, USB/manual input, duplicate-safe attendance submission, and server-time explanation in one focused workflow. The overview and QR tab both remained available through the Arabic RTL navigation.

TypeScript and the production build passed after the change. The authenticated browser review confirmed the overview content and the dedicated QR workflow.
