import { Box, Button, Divider, Stack, Typography } from "@mui/material";
import ShowCurrentData from "./components/ShowCurrentData";
import SignInUp from "./components/SignInUp";
import { useUserId } from "./firebase/FirebaseHook";
import { ComponentType, FC, useCallback, useState } from "react";
import ClientManagement from "./components/ClientManagement";
import { CLIENT_REGISTER_PARAMS } from "./constants";

function App() {
	const [currentPage, setCurrentPage] = useState<PageType | null>(
		CLIENT_REGISTER_PARAMS == null ? null : PAGE_TYPE["/clients"]
	);
	const userId = useUserId();
	const onClickBack = useCallback(() => {
		setCurrentPage(null);
	}, []);
	return (
		<>
			<h1>BIDS-WebRTC Demo App</h1>
			<Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 2 }}>
				<Typography variant="body1">
					あなたのUserID: {userId ?? "未ログイン"}
				</Typography>
				<SignInUp />
			</Stack>
			<Divider />
			<Box>
				{userId &&
					(currentPage == null ? (
						<Stack
							direction="row"
							spacing={2}
							alignItems="center"
							sx={{ my: 2 }}
						>
							{Object.keys(PAGE_TYPE).map((key) => (
								<Button
									key={key}
									variant="outlined"
									onClick={() => setCurrentPage(key as PageType)}
								>
									{key}
								</Button>
							))}
						</Stack>
					) : (
						<Page type={currentPage} onClickBack={onClickBack} />
					))}
			</Box>
		</>
	);
}

export default App;

type PageProps = {
	type: PageType;
	onClickBack: () => void;
};
const Page: FC<PageProps> = ({ type, onClickBack }) => {
	const Component = PAGE_MAP[type];
	return <Component onClickBack={onClickBack} />;
};

const PAGE_MAP = {
	"/clients": ClientManagement,
	"/webrtc": ShowCurrentData,
} as const satisfies Record<string, ComponentType<{ onClickBack: () => void }>>;
type PageType = keyof typeof PAGE_MAP;
const PAGE_TYPE = {
	"/clients": "/clients",
	"/webrtc": "/webrtc",
} as const satisfies Record<string, PageType>;
