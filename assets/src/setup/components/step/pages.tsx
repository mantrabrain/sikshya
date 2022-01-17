import * as React from 'react';

type PagesProps = {
	index: number
}

const Pages = (props: PagesProps) => {

	return (
		<div>
			<div>
				<h1>Pages Step {props.index}</h1>

			</div>
		</div>
	);
};
export default Pages
