<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CustomReviewKindsControl from '@/components/CustomReviewKindsControl.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ALL_CUSTOM_REVIEW_KINDS } from '@/lib/custom-review';
import { store as customReviewsStore } from '@/routes/customReviews';
import type { CustomReviewKind } from '@/types';

/**
 * The dialog that starts a review: the name the app would give it, and the
 * kinds it may hold, both open to change.
 *
 * Every opening starts from a fresh review — the name the listing offered and
 * every kind — and sends both, so the server saves exactly what the learner
 * saw. A name left blank is refused here and by the server; a choice of no
 * kinds cannot be made, because the control never lets the last one go.
 */
const props = defineProps<{
    open: boolean;
    /** The name the listing offered, prefilled and editable. */
    defaultName: string;
}>();

const emit = defineEmits<{
    (event: 'update:open', open: boolean): void;
}>();

const form = useForm<{ name: string; kinds: CustomReviewKind[] }>({
    name: props.defaultName,
    kinds: [...ALL_CUSTOM_REVIEW_KINDS],
});

const kindsError = computed(
    () => form.errors.kinds ?? form.errors['kinds.0'] ?? '',
);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.defaults({
            name: props.defaultName,
            kinds: [...ALL_CUSTOM_REVIEW_KINDS],
        });
        form.reset();
        form.clearErrors();
    },
);

function submit(): void {
    form.post(customReviewsStore.url(), {
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-md">
            <DialogHeader>
                <DialogTitle>New custom review</DialogTitle>
                <DialogDescription>
                    Name it and choose what it may hold. Both can be changed
                    later on its page.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="new-review-name">Name</Label>
                    <Input
                        id="new-review-name"
                        v-model="form.name"
                        maxlength="80"
                        aria-describedby="new-review-name-help"
                        :aria-invalid="form.errors.name !== undefined"
                    />
                    <p
                        id="new-review-name-help"
                        class="text-xs text-muted-foreground"
                    >
                        Keep it short. Up to 80 characters.
                    </p>
                    <p
                        v-if="form.errors.name"
                        class="text-xs font-medium text-destructive"
                        role="alert"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <span class="text-sm font-medium">
                        Kinds this review allows
                    </span>
                    <CustomReviewKindsControl
                        :allowed-kinds="form.kinds"
                        :busy="form.processing"
                        @change="(kinds) => (form.kinds = kinds)"
                    />
                    <p class="text-xs text-muted-foreground">
                        Choose what this review may hold. Every kind is allowed
                        unless you narrow it.
                    </p>
                    <p
                        v-if="kindsError !== ''"
                        class="text-xs font-medium text-destructive"
                        role="alert"
                    >
                        {{ kindsError }}
                    </p>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            class="min-h-11 md:min-h-9"
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        class="min-h-11 md:min-h-9"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creating…' : 'Create review' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
