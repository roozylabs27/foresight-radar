import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { MoreOutlined, DeleteOutlined, EditOutlined } from "@ant-design/icons";
import { Head } from "@inertiajs/react";
import {
    Layout,
    theme,
    Breadcrumb,
    Table,
    Tag,
    Dropdown,
    Space,
    Flex,
} from "antd";
import axios from "axios";
import { useEffect, useState } from "react";

export default function DrivingForce({ auth }) {
    // Import
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();
    const { Content } = Layout;

    // State
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(route("driving-force.fetch-data"));

            if (response.status == 200) {
                setTimeout(() => {
                    setData(response.data.data);
                    setLoading(false);
                }, 500);
            } else {
                console.log(error);
                setLoading(false);
            }
        } catch (error) {
            console.log(error);
            setLoading(false);
        }
    };

    const columnApproved = (text) => {
        const status = text.toLowerCase();
        switch (status) {
            case "pending":
                return <Tag color="processing">{status}</Tag>;
            case "approved":
                return <Tag color="success">{status}</Tag>;
            case "rejected":
                return <Tag color="success">{status}</Tag>;
            default:
                return <Tag color="default">{status}</Tag>;
        }
    };

    const columnAction = () => {
        const actions = [
            {
                key: "edit",
                label: (
                    <Flex gap="middle" vertical={false}>
                        <EditOutlined />
                        Edit
                    </Flex>
                ),
            },
            {
                key: "delete",
                label: (
                    <Flex gap="middle" vertical={false}>
                        <DeleteOutlined />
                        Delete
                    </Flex>
                ),
            },
        ];

        return (
            <Dropdown
                placement="topLeft"
                menu={{
                    items: actions,
                    // onClick: ({ key }) => handleDropdownItemClick(key, record),
                }}
                trigger={["hover"]}
            >
                <a onClick={(e) => e.preventDefault()}>
                    <Space>
                        <MoreOutlined />
                    </Space>
                </a>
            </Dropdown>
        );
    };

    const columns = [
        {
            title: "Date Created",
            dataIndex: "date_created",
            width: 100,
        },
        {
            title: "Dimension",
            dataIndex: "dimension",
            width: 100,
        },
        {
            title: "Keyword",
            dataIndex: "keyword",
            width: 150,
        },
        {
            title: "Description",
            dataIndex: "description",
            width: 100,
        },
        {
            title: "Status",
            dataIndex: "status",
            align: "center",
            width: 60,
            render: columnApproved,
        },
        {
            title: "Admin PIC",
            dataIndex: "created_by",
            align: "center",
            width: 60,
        },
        {
            title: "Remark",
            dataIndex: "remark",
            width: 100,
        },
        {
            title: "",
            key: "operation",
            fixed: "right",
            align: "right",
            width: 20,
            render: columnAction,
        },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Driving Force
                </h2>
            }
        >
            <Head title="Driving Force" />

            <Content
                style={{
                    margin: "24px 16px 0",
                    padding: 24,
                    background: colorBgContainer,
                    borderRadius: borderRadiusLG,
                }}
            >
                <Table
                    dataSource={data}
                    rowKey={(record) => record.uuid}
                    columns={columns}
                    // pagination={tableParams.pagination}
                    scroll={{ x: "max-content", y : 420 }}
                    loading={loading}
                    size="small"
                    // onChange={handleTableChange}
                />
            </Content>
        </AuthenticatedLayout>
    );
}
