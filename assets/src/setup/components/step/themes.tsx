import * as React from 'react';
import {Box, Image, Flex, Button, Link, Heading} from "@chakra-ui/react";
import {__} from '@wordpress/i18n';
import {useState, useEffect} from 'react';
import {ThemeSettings} from "../../types/theme-settings";
import SetupAPI from "../../api/setup-api";
import Paragraph_Skeleton from "../../skeleton/paragraph";
import {CheckCircleIcon} from '@chakra-ui/icons'


const Themes = ( ) => {
	const property = {
		theme_image_url: 'https://i0.wp.com/themes.svn.wordpress.org/pragyan/0.0.8/screenshot.png',
		theme_url: 'https://wordpress.org/themes/pragyan/'
	}

	const [isLoading, setIsLoading] = useState(false);
	const [themeStatus, setThemeStatus] = useState<ThemeSettings>();
	const {initThemeStatus, initThemeAction} = new SetupAPI;
	const callbackCall = (response: any) => {
		setThemeStatus(response);
		setIsLoading(false);
	}
	useEffect(() => {

		let size = !themeStatus ? 0 : Object.keys(themeStatus).length;
		if (size == 0) {
			initThemeStatus(callbackCall, {theme: "pragyan"});

		}
	}, [themeStatus]);

	let size = !themeStatus ? 0 : Object.keys(themeStatus).length;
	if (size < 1) {

		return (<Paragraph_Skeleton/>);

	}
	const install_text = themeStatus.installed ? __('Installed', 'sikshya') : __('Install', 'sikshya');
	const activate_text = themeStatus.activated ? __('Activated', 'sikshya') : __('Activate', 'sikshya');

	const installThemeAction = () => {
		let data = {
			theme: "pragyan",
			action: "install"
		};
		if (!themeStatus.installed && !isLoading) {
			setIsLoading(true);
			initThemeAction(() => {
				initThemeStatus(callbackCall, {theme: "pragyan"});
			}, data);

		}
	}

	const activateThemeAction = () => {
		let data = {
			theme: "pragyan",
			action: "activate"
		};
		if (themeStatus.installed && !themeStatus.activated && !isLoading) {
			setIsLoading(true);
			initThemeAction(() => {
				initThemeStatus(callbackCall, {theme: "pragyan"});
			}, data);
		}
	}
	const activate_disable = !(themeStatus.installed && !themeStatus.activated);
	const install_disable = themeStatus.installed;
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
					<Button size="md" colorScheme="blue" isDisabled={install_disable}
							isLoading={!install_disable && isLoading}
							onClick={installThemeAction}>
						{themeStatus.installed ?
							<CheckCircleIcon w={5} h={5} color="white.500" marginRight="2"/>
							: ""}
						{install_text}
					</Button>

					<Button size="md" marginLeft="5" className="button activate"
							isDisabled={activate_disable} isLoading={!activate_disable && isLoading}
							onClick={activateThemeAction}>
						{themeStatus.installed && themeStatus.activated ?
							<CheckCircleIcon w={5} h={5} color="white.500" marginRight="2"/>
							: ""}
						{activate_text}
					</Button>
				</div>

			</Flex>
		</Box>
	);
};
export default Themes
