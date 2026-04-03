import { CircleAlert, CircleCheck } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { Flash } from '@/types';

export function FlashMessage({ flash }: { flash: Flash }) {
    if (flash.error) {
        return (
            <Alert
                variant="destructive"
                className="border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10"
            >
                <CircleAlert className="text-red-600" />
                <AlertTitle>Action needed</AlertTitle>
                <AlertDescription>{flash.error}</AlertDescription>
            </Alert>
        );
    }

    if (flash.success) {
        return (
            <Alert className="border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                <CircleCheck className="text-emerald-600" />
                <AlertTitle>Success</AlertTitle>
                <AlertDescription className="text-emerald-800 dark:text-emerald-200">
                    {flash.success}
                </AlertDescription>
            </Alert>
        );
    }

    return null;
}
