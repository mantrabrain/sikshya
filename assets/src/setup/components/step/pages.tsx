import * as React from 'react';
import StepBox from "./../parts/stepbox";

type PagesProps = {
	index: number
}

const Pages = (props: PagesProps) => {

	return (
		<StepBox>
			<div>
				<h1>Pages Step {props.index}</h1>

			</div>
		</StepBox>
	);
};
export default Pages
