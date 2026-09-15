import { InfoIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: string;
};

export default function TeamInvitationAlert({ invitation, action }: Props) {
    const { t } = useTranslation();

    return (
        <Alert
            data-test="team-invitation-alert"
            className="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/50 dark:text-blue-100 [&>svg]:text-blue-600 dark:[&>svg]:text-blue-400"
        >
            <InfoIcon />
            <AlertDescription className="text-blue-900 dark:text-blue-100">
                {t('teams.invitation_prompt', { action, team: invitation.teamName })}
            </AlertDescription>
        </Alert>
    );
}
