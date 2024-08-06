import PrioritizingChart from "@/Components/PrioritizingChart";
import Radar from "@/Components/Radar";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { Layout, theme, Breadcrumb, Tabs } from "antd";

export default function Dashboard({ auth }) {
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();

    const { Content } = Layout;

    const onChange = (key) => {
        console.log(key);
    };

    const items = [
        {
            key: "1",
            label: "Prioritizing",
            children: <PrioritizingChart/>,
        },
        {
            key: "2",
            label: "Tab 3",
            children: "Content of Tab Pane 3",
        },
        {
            key: "3",
            label: "Foresight Radar",
            children: <Radar />,
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <Content
                style={{
                    margin: "24px 16px 0",
                }}
            >
                <div
                    style={{
                        paddingBlock: 10,
                        paddingInline: 24,
                        minHeight: 360,
                        background: colorBgContainer,
                        borderRadius: borderRadiusLG,
                    }}
                >
                    <Tabs
                        defaultActiveKey="1"
                        items={items}
                        onChange={onChange}
                    />
                </div>
            </Content>
        </AuthenticatedLayout>
    );
}
