import { memo } from "react";

type ShowCurrentDataProps = {
	userId: string;
};
export default memo<ShowCurrentDataProps>(function ShowCurrentData({ userId }) {
	return (
		<>
			<h1>{userId}</h1>
		</>
	);
});
