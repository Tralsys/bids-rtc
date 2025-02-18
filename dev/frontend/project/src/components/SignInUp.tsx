import {
	createUserWithEmailAndPassword,
	signInWithEmailAndPassword,
	signInWithPopup,
} from "firebase/auth";
import {
	FormEvent,
	memo,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from "react";
import { auth, googleAuthProvider } from "../firebase/firebase";
import {
	Button,
	Dialog,
	Divider,
	IconButton,
	LinearProgress,
	Stack,
	TextField,
	Typography,
} from "@mui/material";
import { MdOpenInNew, MdVisibility, MdVisibilityOff } from "react-icons/md";
import { IoLogoGoogle } from "react-icons/io";

export default memo(function SignInUp() {
	const [isProcessing, setIsProcessing] = useState(false);
	const [open, setOpen] = useState(auth.currentUser == null);
	const onClose = useCallback(() => setOpen(false), []);

	useEffect(() => {
		auth.onAuthStateChanged((user) => {
			setOpen(user == null);
		});
	}, []);

	const onClickSignOut = useCallback(async () => {
		try {
			setIsProcessing(true);
			await auth.signOut();
		} catch (e) {
			console.error("Failed to sign out", e);
			alert("Failed to sign out");
		} finally {
			setIsProcessing(false);
		}
	}, []);

	return (
		<>
			<Button
				loading={isProcessing}
				onClick={onClickSignOut}
				disabled={open}
				variant="outlined"
			>
				Sign Out
			</Button>
			<Dialog open={open} fullWidth maxWidth="sm">
				<DialogContent onClose={onClose} />
			</Dialog>
		</>
	);
});

type DialogContentProps = {
	onClose: () => void;
};
const DialogContent = memo<DialogContentProps>(function DialogContent({
	onClose,
}) {
	const [isProcessing, setIsProcessing] = useState(false);
	const [email, setEmail] = useState("");
	const [password, setPassword] = useState("");
	const [isPasswordVisible, setIsPasswordVisible] = useState(false);

	const passwordErrorMessage = useMemo(
		() => getPasswordErrorMessage(password),
		[password]
	);

	const signInWithGoogle = useCallback(async () => {
		console.log("Sign in with Google");
		try {
			setIsProcessing(true);
			await signInWithPopup(auth, googleAuthProvider);
			onClose();
		} catch (e) {
			console.error("Failed to sign in with Google", e);
			alert("Failed to sign in with Google");
		} finally {
			setIsProcessing(false);
		}
	}, [onClose]);

	const handleSubmit = useCallback(
		async (e: FormEvent<HTMLFormElement>) => {
			e.preventDefault();
			if (passwordErrorMessage != null) {
				alert(passwordErrorMessage);
				return;
			}

			try {
				setIsProcessing(true);
				await signInWithEmailAndPassword(auth, email, password);
				onClose();
			} catch (e) {
				console.error("Failed to sign in with email/password", e);
				alert("Failed to sign in with email/password");
			} finally {
				setIsProcessing(false);
			}
		},
		[email, onClose, password, passwordErrorMessage]
	);
	const onClickSignUp = useCallback(async () => {
		if (passwordErrorMessage != null) {
			alert(passwordErrorMessage);
			return;
		}

		try {
			setIsProcessing(true);
			await createUserWithEmailAndPassword(auth, email, password);
			onClose();
		} catch (e) {
			console.error("Failed to sign up", e);
			alert("Failed to sign up");
		} finally {
			setIsProcessing(false);
		}
	}, [email, onClose, password, passwordErrorMessage]);

	const canSignInUpWithEmail = passwordErrorMessage == null;
	return (
		<Stack component="form" onSubmit={handleSubmit} spacing={2} sx={{ p: 2 }}>
			<Typography variant="h5" textAlign="center">
				BIDS-WebRTC サインイン
			</Typography>
			<Divider />
			<Button
				variant="outlined"
				disabled={isProcessing}
				onClick={signInWithGoogle}
				startIcon={<IoLogoGoogle />}
			>
				Sign in with Google
			</Button>
			<Divider> or </Divider>
			<Stack spacing={2}>
				<TextField
					label="Email"
					type="email"
					autoComplete="email"
					disabled={isProcessing}
					value={email}
					onChange={(e) => setEmail(e.target.value)}
				/>
				<TextField
					label="Password"
					type={isPasswordVisible ? "text" : "password"}
					autoComplete="current-password"
					disabled={isProcessing}
					value={password}
					onChange={(e) => setPassword(e.target.value)}
					error={passwordErrorMessage != null}
					helperText={passwordErrorMessage}
					slotProps={{
						input: {
							endAdornment: (
								<IconButton
									aria-label={
										isPasswordVisible
											? "hide the password"
											: "display the password"
									}
									onClick={() => setIsPasswordVisible((v) => !v)}
								>
									{isPasswordVisible ? <MdVisibilityOff /> : <MdVisibility />}
								</IconButton>
							),
						},
					}}
				/>
			</Stack>
			<Button
				href="https://github.com/Tralsys/bids-rtc/wiki/Terms-of-Use"
				target="_blank"
				variant="outlined"
				size="small"
				endIcon={<MdOpenInNew />}
			>
				利用規約
			</Button>
			<Button type="submit" disabled={!canSignInUpWithEmail || isProcessing}>
				Sign in
			</Button>
			<Button
				variant="outlined"
				disabled={!canSignInUpWithEmail || isProcessing}
				onClick={onClickSignUp}
			>
				Sign Up
			</Button>
			{isProcessing && <LinearProgress />}
		</Stack>
	);
});

const PASSWORD_MIN_LENGTH = 8;
function getPasswordErrorMessage(password: string): string | null {
	if (password.length < PASSWORD_MIN_LENGTH) {
		return `パスワードは${PASSWORD_MIN_LENGTH}文字以上で入力してください`;
	}

	const hasUpperCase = /[A-Z]/.test(password);
	const hasLowerCase = /[a-z]/.test(password);
	const hasDigit = /\d/.test(password);
	if (!(hasUpperCase && hasLowerCase && hasDigit)) {
		return "パスワードは大文字・小文字・数字をそれぞれ1文字以上含めてください";
	}

	return null;
}
