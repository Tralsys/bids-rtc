import { useSyncExternalStore } from "react";
import { auth } from "./firebase";

function subscribeUserId(callback: () => void) {
	return auth.onAuthStateChanged(() => {
		callback();
	});
}
function getCurrentUserId() {
	return auth.currentUser?.uid ?? null;
}
export function useUserId() {
	return useSyncExternalStore(subscribeUserId, getCurrentUserId);
}
