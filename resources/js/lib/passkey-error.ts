import type { TFunction } from 'i18next';

type PasskeyErrorLike = { name: string; message: string } | null;

const INVALID_DOMAIN_PATTERN = /Passkeys can't be used on (.+?)\. For local/;

/**
 * The @laravel/passkeys package throws hardcoded English error messages.
 * Map its known error names to translated copy, falling back to the
 * package's own message for anything unrecognized.
 */
export function translatePasskeyError(
    errorInstance: PasskeyErrorLike,
    fallbackMessage: string | null,
    t: TFunction,
): string | null {
    if (!errorInstance) {
        return fallbackMessage;
    }

    switch (errorInstance.name) {
        case 'NotSupportedError':
            return t('common.passkey_errors.not_supported');
        case 'UserCancelledError':
            return t('common.passkey_errors.cancelled');
        case 'PasskeyExistsError':
            return t('common.passkey_errors.already_registered');
        case 'InvalidDomainError': {
            const domain =
                errorInstance.message.match(INVALID_DOMAIN_PATTERN)?.[1] ??
                (typeof window !== 'undefined'
                    ? window.location.hostname
                    : 'this domain');

            return t('common.passkey_errors.invalid_domain', { domain });
        }
        default:
            return fallbackMessage;
    }
}
