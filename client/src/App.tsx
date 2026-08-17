/* Style: دفء الفصل — غلاف تطبيق RTL خفيف يحافظ على الهوية الهادئة للوحة التحكم. */
import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { Route, Switch } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import { ThemeProvider } from "./contexts/ThemeContext";
import LiveDashboard from "./pages/LiveDashboard";
import NotFound from "./pages/NotFound";

export default function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider defaultTheme="light">
        <TooltipProvider>
          <Toaster />
          <Switch>
            <Route path="/" component={() => <LiveDashboard initialPortal="admin" />} />
            <Route path="/admin/login" component={() => <LiveDashboard initialPortal="admin" />} />
            <Route path="/parent/login" component={() => <LiveDashboard initialPortal="parent" />} />
            <Route path="/student/login" component={() => <LiveDashboard initialPortal="student" />} />
            <Route path="/404" component={NotFound} />
            <Route component={NotFound} />
          </Switch>
        </TooltipProvider>
      </ThemeProvider>
    </ErrorBoundary>
  );
}
