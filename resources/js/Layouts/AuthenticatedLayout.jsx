import React, { useState } from "react";
import {
    LogoutOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    DownOutlined,
    DashboardOutlined,
    RadarChartOutlined,
    UsergroupAddOutlined,
} from "@ant-design/icons";
import { Layout, Menu, theme, Flex, Button, Dropdown, Space } from "antd";
import { Link, usePage } from "@inertiajs/react";
const { Header, Content, Footer, Sider } = Layout;

export default function Authenticated({ user, children }) {
    const { url } = usePage();
    const [items, setItems] = useState([
        {
            label: <Link href={route("dashboard.")}>Dashboard</Link>,
            icon: <DashboardOutlined />,
            key: "/dashboard",
        },
        {
            label: <Link href={route("driving-force.")}>Driving Force</Link>,
            icon: <RadarChartOutlined />,
            key: "/driving-force",
        },
        {
            label: "User Management",
            icon: <UsergroupAddOutlined />,
            key: "/user-management",
            children: [
                {
                    key: "/user-management/user",
                    label: <Link href={route("dashboard.")}>User</Link>,
                },
                {
                    key: "/user-management/role",
                    label: <Link href={route("dashboard.")}>Role</Link>,
                },
            ],
        },
    ]);
    const actions = [
        // {
        //     key: "profile",
        //     label: (
        //         <Flex gap="middle" vertical={false}>
        //             {/* <MessageOutlined /> */}
        //             Profile
        //         </Flex>
        //     ),
        // },
        {
            key: "logout",
            label: (
                <Link href="/logout" method="post" as="button" type="button">
                    <Flex gap="middle" vertical={false}>
                        <LogoutOutlined />
                        Logout
                    </Flex>
                </Link>
            ),
        },
    ];

    const {
        token: { colorBgContainer },
    } = theme.useToken();

    const [collapsed, setCollapsed] = useState(false);

    return (
        <Layout
            style={{
                minHeight: "100vh",
            }}
        >
            <Sider
                breakpoint="lg"
                collapsedWidth="0"
                theme="light"
                width={225}
                collapsed={collapsed}
                collapsible
                trigger={null}
            >
                <div className="demo-logo-vertical h-[32px] m-[16px] bg-slate-500 rounded" />
                <Menu
                    theme="light"
                    mode="inline"
                    defaultSelectedKeys={url}
                    items={items}
                />
            </Sider>
            <Layout>
                <Header
                    style={{
                        padding: 0,
                        background: colorBgContainer,
                    }}
                >
                    <Flex
                        style={{
                            width: "100%",
                        }}
                        justify="space-between"
                        align="flex-start"
                    >
                        <Button
                            type="text"
                            icon={
                                collapsed ? (
                                    <MenuUnfoldOutlined />
                                ) : (
                                    <MenuFoldOutlined />
                                )
                            }
                            onClick={() => setCollapsed(!collapsed)}
                            style={{
                                fontSize: "16px",
                                width: 64,
                                height: 64,
                            }}
                        />
                        <Dropdown
                            placement="topLeft"
                            menu={{
                                items : actions,
                                onClick: ({ key }) => {
                                    handleDropdownItemClick(key, record);
                                },
                            }}
                            trigger={["click"]}
                        >
                            <a
                                style={{
                                    marginRight: "20px",
                                }}
                                onClick={(e) => e.preventDefault()}
                            >
                                <Space>
                                    Hi, {user?.name}
                                    <DownOutlined />
                                </Space>
                            </a>
                        </Dropdown>
                    </Flex>
                </Header>
                {children}
                <Footer
                    style={{
                        textAlign: "center",
                    }}
                >
                    Ant Design ©{new Date().getFullYear()} Created by Ant UED
                </Footer>
            </Layout>
        </Layout>
    );
}
