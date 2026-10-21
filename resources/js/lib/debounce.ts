export function debounce<T extends (...args: any[]) => void>(
    callback: T,
    delay = 300,
): (...args: Parameters<T>) => void {
    let timer: ReturnType<typeof setTimeout> | undefined

    return (...args: Parameters<T>): void => {
        clearTimeout(timer)

        timer = setTimeout(() => {
            callback(...args)
        }, delay)
    }
}
