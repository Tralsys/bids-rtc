import { useEffect, useState, useSyncExternalStore } from "react";
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

export function useUserRole() {
	const [role, setRole] = useState<string | null>(null);
	const userId = useUserId();

	useEffect(() => {
		if (!userId) {
			setRole(null);
			return;
		}

		const fetchRole = async () => {
			const user = auth.currentUser;
			if (!user) {
				setRole(null);
				return;
			}
			try {
				const idTokenResult = await user.getIdTokenResult();
				setRole((idTokenResult.claims.role as string) ?? null);
			} catch (error) {
				console.error("Failed to get user role:", error);
				setRole(null);
			}
		};

		fetchRole();

		// トークンの変更を監視
		const unsubscribe = auth.onIdTokenChanged(() => {
			fetchRole();
		});

		return () => unsubscribe();
	}, [userId]);

	return role;
}

export function useIsAdmin() {
	const role = useUserRole();
	return role === "admin";
}
