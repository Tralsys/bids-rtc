import {
	Box,
	Button,
	Collapse,
	Dialog,
	FormControl,
	IconButton,
	Stack,
	TextField,
	Typography,
} from "@mui/material";
import { FormEvent, memo, useCallback, useMemo, useRef, useState } from "react";
import { CLIENT_REGISTER_PARAMS } from "../constants";
import { clientManagementApi } from "../api";
import { MdContentCopy } from "react-icons/md";
import { ResponseError } from "@tralsys/bids-rtc-signaling-api";

type AddNewClientDialogProps = {
	open: boolean;
	onClose: (isSuccess?: boolean) => void;
};
export default memo<AddNewClientDialogProps>(function AddNewClientDialog({
	open,
	onClose,
}) {
	return (
		<Dialog component="form" open={open}>
			<DialogContent onClose={onClose} />
		</Dialog>
	);
});

const DialogContent = memo<Omit<AddNewClientDialogProps, "open">>(
	function DialogContent({ onClose }) {
		const isLoadingRef = useRef(false);
		const [isLoading, setIsLoading] = useState(false);
		const [appId, setAppId] = useState(CLIENT_REGISTER_PARAMS?.appId ?? "");
		const [clientName, setClientName] = useState("");
		const [token, setToken] = useState<string | undefined>(undefined);

		const appIdErrorMessage = useMemo(() => {
			if (appId === "") return "appIdを入力してください";
			const regex = /\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/;
			// \wはアンダースコアもマッチしてしまうため
			if (!regex.test(appId) || appId.includes("_"))
				return "appIdの形式が正しくありません";
			return undefined;
		}, [appId]);
		const clientNameErrorMessage = useMemo(() => {
			if (clientName === "") return "クライアント名を入力してください";
			if (200 < clientName.length)
				return "クライアント名は200文字以内で入力してください";
			return undefined;
		}, [clientName]);
		const hasError =
			clientNameErrorMessage != null || appIdErrorMessage != null;
		const isCompleted = token != null;

		const onClickSubmit = useCallback(
			async (e: FormEvent) => {
				e.preventDefault();
				if (hasError || isCompleted || isLoadingRef.current) return;
				isLoadingRef.current = true;
				setIsLoading(true);

				try {
					const result = await clientManagementApi.registerClientInfo({
						clientInfo: {
							appId,
							name: clientName,
							// 型制約の設定ミスのため、不要だが設定する
							clientId: "",
							createdAt: new Date(),
						},
					});
					const token = result.refreshToken;
					if (CLIENT_REGISTER_PARAMS?.redirect == null) {
						setToken(token);
					} else {
						const url = new URL(CLIENT_REGISTER_PARAMS.redirect);
						url.searchParams.set("token", token);
						window.location.href = url.toString();
					}
				} catch (e) {
					console.error("Failed to register client info", e);
					const errorMessage =
						e instanceof ResponseError
							? (await e.response.json())?.message
							: undefined;
					alert(`クライアントの登録に失敗しました。${errorMessage ?? ""}`);
				} finally {
					isLoadingRef.current = false;
					setIsLoading(false);
				}
			},
			[appId, clientName, hasError, isCompleted]
		);
		const onClickCancel = useCallback(() => {
			if (CLIENT_REGISTER_PARAMS?.redirect == null) {
				onClose();
			} else {
				window.location.href = CLIENT_REGISTER_PARAMS.redirect;
			}
		}, [onClose]);
		const onClickCopyToken = useCallback(async () => {
			if (token == null) return;
			await navigator.clipboard.writeText(token);
		}, [token]);

		return (
			<Stack sx={{ p: 2 }}>
				<Typography variant="h6" sx={{ mb: 1 }}>
					新しいクライアントを追加
				</Typography>
				<Collapse in={token == null}>
					<Stack spacing={2} useFlexGap>
						<FormControl fullWidth>
							<TextField
								label="Application ID"
								value={appId}
								onChange={
									CLIENT_REGISTER_PARAMS == null
										? (e) => setAppId(e.target.value)
										: undefined
								}
								disabled={
									isLoading || isCompleted || CLIENT_REGISTER_PARAMS != null
								}
								error={appIdErrorMessage != null}
								helperText={appIdErrorMessage ?? ""}
							/>
						</FormControl>
						<FormControl fullWidth>
							<TextField
								label="クライアント名"
								value={clientName}
								onChange={(e) => setClientName(e.target.value)}
								disabled={isLoading || isCompleted}
								error={clientNameErrorMessage != null}
								helperText={clientNameErrorMessage ?? ""}
							/>
						</FormControl>
					</Stack>
					<Stack
						spacing={2}
						direction="row"
						sx={{ justifyContent: "flex-end", pt: 1 }}
					>
						<Button
							type="button"
							disabled={isLoading || isCompleted}
							onClick={onClickCancel}
						>
							キャンセル
						</Button>
						<Button
							type="submit"
							disabled={isLoading || isCompleted || hasError}
							loading={isLoading}
							variant="contained"
							onClick={onClickSubmit}
						>
							追加
						</Button>
					</Stack>
				</Collapse>
				<Collapse in={token != null}>
					<Stack spacing={1}>
						<Typography>
							クライアントを追加しました。以下のトークンをクライアントにセットしてください。
						</Typography>
						<Typography color="error">
							※トークンは一度しか表示されません。コピーを忘れないようにしてください。
						</Typography>
						<FormControl fullWidth>
							<TextField
								label="トークン"
								disabled
								value={token ?? ""}
								slotProps={{
									input: {
										endAdornment: (
											<IconButton onClick={onClickCopyToken}>
												<MdContentCopy />
											</IconButton>
										),
									},
								}}
							/>
						</FormControl>
						<Box sx={{ display: "flex", justifyContent: "flex-end" }}>
							<Button onClick={() => onClose(true)} variant="outlined">
								閉じる
							</Button>
						</Box>
					</Stack>
				</Collapse>
			</Stack>
		);
	}
);
