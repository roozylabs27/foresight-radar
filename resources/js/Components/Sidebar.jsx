import React, { useState } from "react";
import { Layout, Menu } from "antd";
import { UsergroupAddOutlined, DashboardOutlined, RadarChartOutlined } from "@ant-design/icons";
import { Link, usePage } from "@inertiajs/react";

export default function Sidebar({ collapsed }) {
    const { Sider } = Layout;
    const { url } = usePage();

    const [items, setItems] = useState([
        {
            label: <Link href={route("dashboard.")}>Dashboard</Link>,
            icon: <DashboardOutlined />,
            key: "/dashboard",
        },
        {
            label: <Link href={route("dashboard.")}>Dimension</Link>,
            icon: <RadarChartOutlined />,
            key: "/driven",
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
                {
                    key: "/user-management/permission",
                    label: <Link href={route("dashboard.")}>Permission</Link>,
                },
            ],
        },
    ]);
    const [theme, setTheme] = useState("light");

    return (
        <>
            <Sider
                breakpoint="lg"
                collapsedWidth="0"
                theme={theme}
                width={225}
                collapsed={collapsed}
                collapsible
                trigger={null}
            >
                <div className="demo-logo-vertical" />
                <Menu
                    theme={theme}
                    mode="inline"
                    defaultSelectedKeys={url}
                    items={items}
                />
            </Sider>
        </>
    );
}
