import { describe, expect, it } from 'vitest';
import { evaluatePassword, parsePasswordRules } from './password-strength';

const strict =
    'minlength: 12; required: lower; required: upper; required: digit; required: special;';

describe('parsePasswordRules', () => {
    it('parses the Laravel rules string', () => {
        expect(parsePasswordRules(strict)).toEqual({
            minLength: 12,
            required: ['lower', 'upper', 'digit', 'special'],
        });
    });

    it('falls back to a minimum of 8 with no requirements', () => {
        expect(parsePasswordRules('')).toEqual({ minLength: 8, required: [] });
    });
});

describe('evaluatePassword', () => {
    it('is empty for an empty password', () => {
        const result = evaluatePassword('', strict);

        expect(result.level).toBe('empty');
        expect(result.score).toBe(0);
        expect(result.checks.every((check) => !check.met)).toBe(true);
    });

    it('marks each unmet requirement', () => {
        const result = evaluatePassword('abc', strict);

        expect(
            result.checks
                .filter((check) => check.met)
                .map((c) => c.requirement),
        ).toEqual(['lower']);
        expect(result.level).toBe('weak');
    });

    it('is fair when at least half of the checks pass', () => {
        expect(evaluatePassword('abcdefghijkl1', strict).level).toBe('fair');
    });

    it('is good when every check passes at the minimum length', () => {
        expect(evaluatePassword('Abcdefgh1!xy', strict).level).toBe('good');
    });

    it('is strong when well over the minimum length', () => {
        expect(evaluatePassword('Abcdefgh1!xyzzzzz', strict).level).toBe(
            'strong',
        );
    });

    it('only checks length when no character rules apply', () => {
        const result = evaluatePassword('abcdefgh', 'minlength: 8;');

        expect(result.checks).toHaveLength(1);
        expect(result.checks[0].met).toBe(true);
    });
});
