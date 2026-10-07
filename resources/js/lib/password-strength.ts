export type PasswordRequirement =
    'min_length' | 'lower' | 'upper' | 'digit' | 'special';

export type PasswordStrengthLevel =
    'empty' | 'weak' | 'fair' | 'good' | 'strong';

export type PasswordCheck = {
    requirement: PasswordRequirement;
    met: boolean;
    minLength?: number;
};

export type PasswordEvaluation = {
    checks: PasswordCheck[];
    level: PasswordStrengthLevel;
    /** 0 to 4, drives the number of filled meter segments. */
    score: number;
};

const specialPattern = /[^\p{L}\p{N}\s]/u;

const requirementTests: Record<
    Exclude<PasswordRequirement, 'min_length'>,
    (password: string) => boolean
> = {
    lower: (password) => /\p{Ll}/u.test(password),
    upper: (password) => /\p{Lu}/u.test(password),
    digit: (password) => /\p{N}/u.test(password),
    special: (password) => specialPattern.test(password),
};

/**
 * Parse Laravel's `Password::toPasswordRulesString()` output,
 * e.g. "minlength: 12; required: lower; required: digit;".
 */
export function parsePasswordRules(rules: string): {
    minLength: number;
    required: Exclude<PasswordRequirement, 'min_length'>[];
} {
    let minLength = 8;
    const required: Exclude<PasswordRequirement, 'min_length'>[] = [];

    for (const part of rules.split(';')) {
        const [key, value] = part.split(':').map((piece) => piece.trim());

        if (key === 'minlength' && Number.parseInt(value, 10) > 0) {
            minLength = Number.parseInt(value, 10);
        }

        if (key === 'required' && value in requirementTests) {
            required.push(value as Exclude<PasswordRequirement, 'min_length'>);
        }
    }

    return { minLength, required };
}

export function evaluatePassword(
    password: string,
    rules: string,
): PasswordEvaluation {
    const { minLength, required } = parsePasswordRules(rules);

    const checks: PasswordCheck[] = [
        {
            requirement: 'min_length',
            met: password.length >= minLength,
            minLength,
        },
        ...required.map((requirement) => ({
            requirement,
            met: requirementTests[requirement](password),
        })),
    ];

    if (password === '') {
        return { checks, level: 'empty', score: 0 };
    }

    const metCount = checks.filter((check) => check.met).length;
    const allMet = metCount === checks.length;
    const varietyCount = Object.values(requirementTests).filter((test) =>
        test(password),
    ).length;

    if (allMet && password.length >= minLength + 4 && varietyCount >= 3) {
        return { checks, level: 'strong', score: 4 };
    }

    if (allMet) {
        return { checks, level: 'good', score: 3 };
    }

    if (metCount / checks.length >= 0.5) {
        return { checks, level: 'fair', score: 2 };
    }

    return { checks, level: 'weak', score: 1 };
}
