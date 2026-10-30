<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({teachers:Array,grades:Array,subjects:Array,categories:Array});
const editing = ref(null);
const deleting = ref(null);
const all = ref({grades:false,subjects:true,categories:false});
const form = useForm({name:'',email:'',phone:'',password:'',is_active:true,can_use_assistant:true,allowed_grades:[],allowed_subjects:null,allowed_categories:['exercise']});
const visibleSubjects = computed(()=>props.subjects.filter(s=>all.value.grades || form.allowed_grades.includes(s.grade?.number)));
const labels = {exercise:'Textbook exercises',additional:'Additional questions',past_paper:'Past papers'};
function reset(){editing.value=null;all.value={grades:false,subjects:true,categories:false};form.defaults({name:'',email:'',phone:'',password:'',is_active:true,can_use_assistant:true,allowed_grades:[],allowed_subjects:null,allowed_categories:['exercise']});form.reset();form.clearErrors();}
function edit(teacher){
    editing.value=teacher.id;
    const permissions=teacher.teacher_permission ?? {};
    form.defaults({name:teacher.name,email:teacher.email ?? '',phone:teacher.phone ?? '',password:'',is_active:teacher.is_active,can_use_assistant:permissions.can_use_assistant ?? true,
        allowed_grades:permissions.allowed_grades ?? null,allowed_subjects:permissions.allowed_subjects ?? null,allowed_categories:permissions.allowed_categories ?? null});
    form.reset();form.clearErrors();
    all.value={grades:permissions.allowed_grades==null,subjects:permissions.allowed_subjects==null,categories:permissions.allowed_categories==null};
    document.getElementById('teacher-form')?.scrollIntoView({behavior:'smooth'});
}
function toggleAll(key,field){form[field]=all.value[key]?null:[];}
function submit(){
    const url=editing.value?route('admin.teachers.update',editing.value):route('admin.teachers.store');
    const options={preserveScroll:true,onSuccess:()=>{form.defaults({name:'',email:'',phone:'',password:'',is_active:true,can_use_assistant:true,allowed_grades:[],allowed_subjects:null,allowed_categories:['exercise']});reset();}};
    editing.value?form.patch(url,options):form.post(url,options);
}
function remove(){router.delete(route('admin.teachers.destroy',deleting.value.id),{preserveScroll:true,onSuccess:()=>{deleting.value=null;},onError:()=>{deleting.value=null;}});}
function summary(values,kind){
    if(values==null)return 'All '+kind;
    if(!values.length)return 'No '+kind+' access';
    if(kind==='classes')return values.map(n=>'Class '+n).join(', ');
    if(kind==='subjects')return props.subjects.filter(s=>values.includes(s.id)).map(s=>s.name_en+' ('+(s.grade?.label_en ?? '')+')').join(', ');
    return values.map(v=>labels[v] ?? v).join(', ');
}
</script>
<template>
    <Head title="Teachers & permissions" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Teachers & permissions</h2></template>
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-8">
            <form id="teacher-form" class="space-y-5 rounded-xl border bg-white p-6 shadow-sm" @submit.prevent="submit">
                <h3 class="text-lg font-semibold">{{ editing ? 'Edit teacher' : 'Add teacher' }}</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm">Name<input v-model="form.name" required class="mt-1 block w-full rounded-lg border-gray-300" /><InputError :message="form.errors.name" /></label>
                    <label class="text-sm">Email<input v-model="form.email" type="email" class="mt-1 block w-full rounded-lg border-gray-300" /><InputError :message="form.errors.email" /></label>
                    <label class="text-sm">Phone<input v-model="form.phone" class="mt-1 block w-full rounded-lg border-gray-300" /><InputError :message="form.errors.phone" /></label>
                    <label class="text-sm">{{ editing ? 'New password (leave blank to keep)' : 'Password' }}<input v-model="form.password" type="password" minlength="8" :required="!editing" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-gray-300" /><InputError :message="form.errors.password" /></label>
                </div>
                <p class="text-sm text-slate-500">Choose what this teacher can access. An empty selection revokes that access. Select “All” to allow everything in a group.</p>
                <div class="grid gap-4 lg:grid-cols-3">
                    <fieldset class="rounded-lg border p-4"><legend class="px-1 font-semibold">Classes</legend>
                        <label class="flex gap-2 text-sm"><input v-model="all.grades" type="checkbox" @change="toggleAll('grades','allowed_grades')" />All classes</label>
                        <div v-if="!all.grades" class="mt-3 max-h-52 space-y-2 overflow-auto"><label v-for="grade in grades" :key="grade.id" class="flex gap-2 text-sm"><input v-model="form.allowed_grades" type="checkbox" :value="grade.number" />{{ grade.label_en }}</label></div>
                        <InputError :message="form.errors.allowed_grades || form.errors['allowed_grades.0']" />
                    </fieldset>
                    <fieldset class="rounded-lg border p-4"><legend class="px-1 font-semibold">Subjects</legend>
                        <label class="flex gap-2 text-sm"><input v-model="all.subjects" type="checkbox" @change="toggleAll('subjects','allowed_subjects')" />All subjects in allowed classes</label>
                        <div v-if="!all.subjects" class="mt-3 max-h-52 space-y-2 overflow-auto"><label v-for="subject in visibleSubjects" :key="subject.id" class="flex gap-2 text-sm"><input v-model="form.allowed_subjects" type="checkbox" :value="subject.id" />{{ subject.name_en }} · {{ subject.grade?.label_en }}</label><p v-if="!visibleSubjects.length" class="text-xs text-slate-500">Select classes first.</p></div>
                        <InputError :message="form.errors.allowed_subjects || form.errors['allowed_subjects.0']" />
                    </fieldset>
                    <fieldset class="rounded-lg border p-4"><legend class="px-1 font-semibold">Question sources</legend>
                        <label class="flex gap-2 text-sm"><input v-model="all.categories" type="checkbox" @change="toggleAll('categories','allowed_categories')" />All sources</label>
                        <div v-if="!all.categories" class="mt-3 space-y-2"><label v-for="category in categories" :key="category" class="flex gap-2 text-sm"><input v-model="form.allowed_categories" type="checkbox" :value="category" />{{ labels[category] }}</label></div>
                        <InputError :message="form.errors.allowed_categories || form.errors['allowed_categories.0']" />
                    </fieldset>
                </div>
                <div><label class="flex items-center gap-2 text-sm"><input v-model="form.can_use_assistant" type="checkbox" />Question Assistant access</label><p class="mt-1 text-xs text-slate-500">Uses the school’s shared request budget. Class, subject and source permissions still apply.</p><InputError :message="form.errors.can_use_assistant" /></div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" />Account active — turn off to revoke access immediately</label>
                <div class="flex gap-3"><button :disabled="form.processing" class="rounded-lg bg-indigo-600 px-5 py-2 text-white disabled:opacity-50">{{ form.processing ? 'Saving…' : editing ? 'Save teacher & permissions' : 'Create teacher' }}</button><button v-if="editing" type="button" class="rounded-lg border px-4 py-2" @click="reset">Cancel editing</button></div>
            </form>
            <InputError :message="$page.props.errors.teacher" />
            <div class="divide-y rounded-xl border bg-white shadow-sm">
                <div v-for="teacher in teachers" :key="teacher.id" class="flex flex-wrap justify-between gap-4 p-5">
                    <div class="min-w-0 flex-1"><p class="font-semibold">{{ teacher.name }} <span :class="teacher.is_active ? 'text-green-700' : 'text-red-700'" class="ml-2 text-xs">{{ teacher.is_active ? 'Active' : 'Inactive' }}</span></p><p class="text-sm text-slate-500">{{ teacher.email || teacher.phone }}</p>
                        <p class="mt-2 text-xs text-slate-600">{{ summary(teacher.teacher_permission?.allowed_grades,'classes') }}</p><p class="mt-1 text-xs text-slate-600">{{ summary(teacher.teacher_permission?.allowed_subjects,'subjects') }}</p><p class="mt-1 text-xs text-slate-600">{{ summary(teacher.teacher_permission?.allowed_categories,'sources') }}</p>
                    <p class="mt-1 text-xs text-slate-600">Question Assistant: {{ teacher.teacher_permission?.can_use_assistant === false ? 'No access' : 'Allowed' }}</p></div>
                    <div class="flex items-start gap-2"><button class="rounded-lg border px-3 py-2 text-sm" @click="edit(teacher)">Edit / permissions</button><button class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700" @click="deleting=teacher">Delete</button></div>
                </div><p v-if="!teachers.length" class="p-6 text-sm text-slate-500">No teachers yet. Create the first teacher above.</p>
            </div>
            <div v-if="deleting" role="alertdialog" aria-label="Delete teacher" class="rounded-xl border border-red-200 bg-red-50 p-5"><p>Delete {{ deleting.name }}? This cannot be undone. Teachers with saved papers or imports must be deactivated instead.</p><div class="mt-3 flex gap-2"><button class="rounded-lg bg-red-600 px-4 py-2 text-white" @click="remove">Confirm delete</button><button class="rounded-lg border px-4 py-2" @click="deleting=null">Cancel</button></div></div>
        </div>
    </AuthenticatedLayout>
</template>
