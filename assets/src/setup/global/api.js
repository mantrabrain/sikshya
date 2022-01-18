import apiFetch from "@wordpress/api-fetch";

const sikshyaAPIFetch = () => {
 	apiFetch({
		path: '/wp/v2/posts/1',
		method: 'POST',
		data: {title: 'New Post Title'},
	}).then((res) => {
		console.log(res);
	});
};

export default sikshyaAPIFetch;
