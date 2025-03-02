import { useLayoutEffect, useState } from "react";

export function useAbortOnUnmount() {
	const [abortSignal, setAbortSignal] = useState<AbortSignal | undefined>(
		undefined
	);
	useLayoutEffect(() => {
		const abortController = new AbortController();
		setAbortSignal(abortController.signal);
		return () => {
			abortController.abort();
		};
	}, []);
	return abortSignal;
}
