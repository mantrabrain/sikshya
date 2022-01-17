import * as React from 'react';
import {Box, Image, Flex, Button, Link, Heading} from "@chakra-ui/react";
import {__} from '@wordpress/i18n';

type ThemesProps = {
	index: number
}

const Themes = (props: ThemesProps) => {
	const property = {
		theme_image_url: 'https://i0.wp.com/themes.svn.wordpress.org/pragyan/0.0.8/screenshot.png',
		theme_url: 'https://wordpress.org/themes/pragyan/'
	}
	return (
		<Box maxW='full' borderWidth='1px' borderRadius='lg' overflow='hidden' margin="0 auto">
			<Link href={property.theme_url} target="_blank">
				<Image src={property.theme_image_url}
					   alt="Pragyan WordPress Theme"/>
			</Link>
			<Flex width="100%" justify="space-between" align="center" gap={10} p={10}>

				<Link href={property.theme_url} target="_blank"><Heading as="h2"
																		 size="lg">Pragyan</Heading></Link>

				<div className="theme-actions">
					<Button size="md" colorScheme="blue">
						{__('Install', 'sikshya')}
					</Button>

					<Button size="md" marginLeft="5" className="button activate">
						{__('Activate', 'sikshya')}
					</Button>
				</div>

			</Flex>
		</Box>
	);
};
export default Themes
