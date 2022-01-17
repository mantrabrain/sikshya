import * as React from 'react';

type FinishProps = {
	index: number
}

const Finish = (props: FinishProps) => {

	return (
		<div>
			<div>
				<h1>Finish Step {props.index}</h1>

			</div>
		</div>
	);
};
export default Finish
