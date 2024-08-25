import React, { useState } from "react";
import {
    LogoutOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    DownOutlined,
    DashboardOutlined,
    RadarChartOutlined,
    UsergroupAddOutlined,
    FieldTimeOutlined,
    StarOutlined,
    UserSwitchOutlined,
    FileProtectOutlined,
    FileExcelOutlined,
} from "@ant-design/icons";
import { Layout, Menu, theme, Flex, Button, Dropdown, Space } from "antd";
import { Link, usePage } from "@inertiajs/react";
const { Header, Content, Footer, Sider } = Layout;

export default function Authenticated({ auth, children }) {
    const { url } = usePage();
    const { user, permissions } = auth;
    const [items, setItems] = useState([
        {
            label: <Link href={route("dashboard.")}>Dashboard</Link>,
            icon: <DashboardOutlined />,
            key: "/dashboard",
            permission: "view-dashboard",
        },
        {
            label: <Link href={route("driving-force.")}>Driving Force</Link>,
            icon: <RadarChartOutlined />,
            key: "/driving-force",
            permission: "view-driving-force",
        },
        {
            label: <Link href={route("time-horizon.")}>Time Horizon</Link>,
            icon: <FieldTimeOutlined />,
            key: "/time-horizon",
            permission: "view-time-horizon",
        },
        {
            label: (
                <Link href={route("rating-urgency.")}>Rating of Urgency</Link>
            ),
            icon: <StarOutlined />,
            key: "/rating-urgency",
            permission: "view-rating-urgency",
        },
        {
            label: <Link href={route("status-action.")}>Status of Action</Link>,
            icon: <FieldTimeOutlined />,
            key: "/status-action",
            permission: "view-status-action",
        },
        {
            label: <Link href={route("approval.")}>Approval</Link>,
            icon: <FileProtectOutlined />,
            key: "/approval",
            permission: "view-approval",
        },
        {
            label: <Link href={route("closed-items.")}>Closed Items</Link>,
            icon: <FileExcelOutlined />,
            key: "/closed-items",
            permission: "view-closed-items",
        },
        {
            label: "User Management",
            icon: <UsergroupAddOutlined />,
            key: "/user-management",
            permission: "view-user",
            children: [
                {
                    key: "/user-management/user",
                    label: (
                        <Link href={route("user-management.user.")}>User</Link>
                    ),
                },
                // {
                //     key: "/user-management/role",
                //     label: <Link href={route("user-management.role.")}>Role</Link>,
                // },
            ],
        },
    ]);
    const actions = [
        // {
        //     key: "profile",
        //     label: (
        //         <Link href={route("user-management.user.profile.")}>
        //             <Flex gap="middle" vertical={false}>
        //                 <UserSwitchOutlined />
        //                 Profile
        //             </Flex>
        //         </Link>
        //     ),
        // },
        {
            key: "logout",
            label: (
                <Link href={route("logout")} method="post" type="button">
                    <Flex gap="middle" vertical={false}>
                        <LogoutOutlined />
                        Logout
                    </Flex>
                </Link>
            ),
        },
    ];

    const filteredItems = items.filter((item) =>
        permissions.includes(item.permission)
    );

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
                    items={filteredItems}
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
                                items: actions,
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
                    Foresight Radar ©{new Date().getFullYear()} All Right
                    Reserved.
                </Footer>
            </Layout>
        </Layout>
    );
}
