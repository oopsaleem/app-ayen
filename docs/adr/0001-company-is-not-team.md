# Company is a new entity, not the existing Team system

This app already has a generic `teams`/`team_members`/`team_invitations` system (free-text role
string, `is_personal` flag, `current_team_id` on users) that looks like it could serve as
"Company." We decided against reusing it: Team's role model is a single free-text string, which
doesn't fit the domain's additive Admin/Manager/Chef role-table pattern, and Team may serve an
unrelated generic-workspace purpose elsewhere in the app that we don't want to couple the
restaurant domain to. Company is a new standalone table; Team is left untouched and unrelated.
