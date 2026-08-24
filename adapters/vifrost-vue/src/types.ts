import type { Ref } from 'vue'

/** Shape returned by both {@link useModel} and {@link useQuery}. */
export interface AsyncResource<T> {
  data: Ref<T>
  error: Ref<unknown>
  isLoading: Ref<boolean>
  refetch: () => Promise<void>
}
