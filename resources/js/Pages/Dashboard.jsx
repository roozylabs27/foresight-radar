import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { Layout, theme, Breadcrumb } from "antd";

export default function Dashboard({ auth }) {
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();

    const { Content } = Layout;

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
                        padding: 24,
                        minHeight: 360,
                        background: colorBgContainer,
                        borderRadius: borderRadiusLG,
                    }}
                >
                    content
                </div>
            </Content>
        </AuthenticatedLayout>
    );
}
