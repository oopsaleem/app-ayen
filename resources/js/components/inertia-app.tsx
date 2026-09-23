import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/use-flash-toast';

export function InertiaApp({ children }: { children: React.ReactNode }) {
    useFlashToast();

    return (
        <TooltipProvider delayDuration={0}>
            {children}
            <Toaster />
        </TooltipProvider>
    );
}

export default InertiaApp;
