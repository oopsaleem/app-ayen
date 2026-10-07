import { Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, Ref } from 'react';
import { useEffect, useRef, useState } from 'react';
import PasswordChecklist from '@/components/password-checklist';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<'input'>, 'type'> & {
    ref?: Ref<HTMLInputElement>;
    passwordrules?: string;
    showChecklist?: boolean;
};

export default function PasswordInput({
    className,
    ref,
    showChecklist = false,
    onChange,
    ...props
}: Props) {
    const [showPassword, setShowPassword] = useState(false);
    const [value, setValue] = useState('');
    const inputRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        const form = inputRef.current?.form;

        if (!showChecklist || !form) {
            return;
        }

        const syncAfterReset = () =>
            requestAnimationFrame(() =>
                setValue(inputRef.current?.value ?? ''),
            );

        form.addEventListener('reset', syncAfterReset);

        return () => form.removeEventListener('reset', syncAfterReset);
    }, [showChecklist]);

    const setRefs = (element: HTMLInputElement | null) => {
        inputRef.current = element;

        if (typeof ref === 'function') {
            ref(element);
        } else if (ref) {
            ref.current = element;
        }
    };

    const input = (
        <div className="relative">
            <Input
                type={showPassword ? 'text' : 'password'}
                className={cn('pr-10', className)}
                ref={setRefs}
                onChange={(event) => {
                    setValue(event.target.value);
                    onChange?.(event);
                }}
                {...props}
            />
            <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                tabIndex={-1}
            >
                {showPassword ? (
                    <EyeOff className="size-4" />
                ) : (
                    <Eye className="size-4" />
                )}
            </button>
        </div>
    );

    if (!showChecklist) {
        return input;
    }

    return (
        <div className="grid gap-3">
            {input}
            <PasswordChecklist
                password={value}
                rules={props.passwordrules ?? ''}
            />
        </div>
    );
}
