import { usePage } from '@inertiajs/vue3';

export function useTeacherActions() {
    const page = usePage();
    return action => page.props.auth?.user?.teacher_actions?.[action] !== false;
}
