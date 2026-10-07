import { Check, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { evaluatePassword } from '@/lib/password-strength';
import type { PasswordStrengthLevel } from '@/lib/password-strength';
import { cn } from '@/lib/utils';

const levelColors: Record<PasswordStrengthLevel, string> = {
    empty: 'bg-muted',
    weak: 'bg-destructive',
    fair: 'bg-amber-500',
    good: 'bg-lime-500',
    strong: 'bg-emerald-600',
};

export default function PasswordChecklist({
    password,
    rules,
}: {
    password: string;
    rules: string;
}) {
    const { t } = useTranslation();
    const { checks, level, score } = evaluatePassword(password, rules);

    return (
        <div className="grid gap-2" data-test="password-checklist">
            <div className="flex items-center gap-2">
                <div
                    className="flex flex-1 gap-1"
                    role="meter"
                    aria-label={t('password_strength.title')}
                    aria-valuemin={0}
                    aria-valuemax={4}
                    aria-valuenow={score}
                    aria-valuetext={t(`password_strength.levels.${level}`)}
                >
                    {[1, 2, 3, 4].map((segment) => (
                        <div
                            key={segment}
                            className={cn(
                                'h-1.5 flex-1 rounded-full transition-colors',
                                segment <= score
                                    ? levelColors[level]
                                    : 'bg-muted',
                            )}
                        />
                    ))}
                </div>
                <span className="text-xs text-muted-foreground">
                    {t(`password_strength.levels.${level}`)}
                </span>
            </div>
            <ul className="grid gap-1 text-sm" aria-live="polite">
                {checks.map((check) => (
                    <li
                        key={check.requirement}
                        data-met={check.met}
                        className={cn(
                            'flex items-center gap-2',
                            check.met
                                ? 'text-emerald-600 dark:text-emerald-500'
                                : 'text-muted-foreground',
                        )}
                    >
                        {check.met ? (
                            <Check className="size-4" aria-hidden />
                        ) : (
                            <X className="size-4" aria-hidden />
                        )}
                        {t(
                            `password_strength.requirements.${check.requirement}`,
                            {
                                count: check.minLength,
                            },
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
